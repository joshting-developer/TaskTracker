<?php

namespace App\Repositories;

use App\Database\DatabaseTimes;
use App\Domain\Fields;
use Illuminate\Support\Facades\DB;

/** 管理狀態、留言、關聯、收錄與歷史子表，批次讀取避免逐任務N+1。 */
class RelationalChildren
{
    private array $rows = [];

    /** 批次預載單一種類的子資料。
     * @param  string  $kind  實體種類
     * @param  array  $ids  資料庫內部主鍵
     * @return void
     */
    public function prepare(string $kind, array $ids): void
    {
        $this->rows = [];
        $tables = match ($kind) {
            'projects' => ['project_statuses' => 'project_id'],
            'issues' => [
                'issue_comments' => 'issue_id',
                'issue_relations' => 'issue_id',
            ],
            'boards' => ['board_issues' => 'board_id'],
            default => [],
        };
        foreach ($tables as $table => $parent) {
            $this->rows[$table] = DB::table($table)->whereIn($parent, $ids)->orderBy('position')->get()->groupBy($parent)->all();
        }
        $this->rows['audit_events'] = DB::table('audit_events')->where('entity_type', $kind)->whereIn('entity_id', $ids)->orderBy('position')->get()->groupBy('entity_id')->all();
    }

    /** 將已批次取得的子表還原成既有DTO集合。
     * @param  string  $kind  實體種類
     * @param  object  $row  主表紀錄
     * @param  RelationalRecords  $records  受租戶限制的識別碼轉換入口
     * @return array
     */
    public function read(string $kind, object $row, RelationalRecords $records): array
    {
        $result = [];
        if ($kind === 'projects') {
            $result['statuses'] = array_map(fn ($state) => [
                'id' => $state->code,
                'label' => $state->label,
                'color' => $state->color,
            ], $this->items('project_statuses', $row->id));
            $result['settingsHistory'] = [];
        }
        if ($kind === 'issues') {
            $result['relatedIssueIds'] = array_map(fn ($edge) => $records->publicId('issues', $edge->related_issue_id), $this->items('issue_relations', $row->id));
            $result['comments'] = array_map(fn ($comment) => [
                'id' => $comment->public_id,
                'content' => $comment->content,
                'author' => $comment->author,
                'createdAt' => DatabaseTimes::public($comment->created_at),
            ], $this->items('issue_comments', $row->id));
            $result['history'] = [];
        }
        if ($kind === 'boards') {
            $result['selectedIssueIds'] = array_map(fn ($item) => $records->publicId('issues', $item->issue_id), $this->items('board_issues', $row->id));
            $result['isDefault'] = false;
        }
        if (in_array($kind, ['timeEntries', 'todos', 'projectAcls'], true)) {
            $result['history'] = [];
        }
        foreach ($this->items('audit_events', $row->id) as $event) {
            $result[$event->stream][] = json_decode($event->event_data, true, flags: JSON_THROW_ON_ERROR);
        }

        return $result;
    }

    /** 取得預載集合，不在迴圈內追加SQL。
     * @param  string  $table  子表
     * @param  int  $id  父表主鍵
     * @return array
     */
    private function items(string $table, int $id): array
    {
        return isset($this->rows[$table][$id]) ? $this->rows[$table][$id]->all() : [];
    }

    /** 在父表同一交易同步狀態、留言、關聯與歷史。
     * @param  string  $kind  實體種類
     * @param  object  $row  父表紀錄
     * @param  array  $value  已驗證的DTO
     * @param  RelationalRecords  $records  租戶資料入口
     * @return void
     */
    public function write(string $kind, object $row, array $value, RelationalRecords $records): void
    {
        if ($kind === 'projects') {
            $statuses = $value['statuses'] ?? Fields::STATES;
            foreach ($statuses as $position => $state) {
                DB::table('project_statuses')->updateOrInsert([
                    'project_id' => $row->id,
                    'code' => $state['id'],
                ], [
                    'workspace_id' => $row->workspace_id,
                    'label' => $state['label'],
                    'category' => $state['id'] === 'closed' ? 'done' : 'active',
                    'color' => $state['color'],
                    'position' => $position,
                ]);
            }
            DB::table('project_statuses')->where('project_id', $row->id)->whereNotIn('code', array_column($statuses, 'id'))->delete();
        }
        if ($kind === 'issues') {
            $comments = [];
            foreach ($value['comments'] ?? [] as $position => $comment) {
                $comments[] = [
                    'public_id' => $comment['id'], 'content' => $comment['content'],
                    'author' => $comment['author'], 'created_at' => DatabaseTimes::store($comment['createdAt'] ?? null),
                    'position' => $position,
                ];
            }
            $this->syncRows('issue_comments', ['issue_id' => $row->id], 'public_id', $comments);
            $relations = [];
            foreach ($value['relatedIssueIds'] ?? [] as $position => $id) {
                $relations[] = [
                    'workspace_id' => $row->workspace_id, 'project_id' => $row->project_id,
                    'related_issue_id' => $records->internalId('issues', $id), 'position' => $position,
                ];
            }
            $this->syncRows('issue_relations', ['issue_id' => $row->id], 'related_issue_id', $relations);
        }
        if ($kind === 'boards') {
            $items = [];
            foreach ($value['selectedIssueIds'] ?? [] as $position => $id) {
                $items[] = [
                    'workspace_id' => $row->workspace_id, 'project_id' => $row->project_id,
                    'issue_id' => $records->internalId('issues', $id), 'position' => $position,
                ];
            }
            $this->syncRows('board_issues', ['board_id' => $row->id], 'issue_id', $items);
        }
        foreach (['history', 'settingsHistory', 'detailsHistory'] as $stream) {
            if (! array_key_exists($stream, $value)) {
                continue;
            }
            $events = [];
            foreach ($value[$stream] as $position => $event) {
                $events[] = [
                    'workspace_id' => $row->workspace_id, 'position' => $position,
                    'event_data' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ];
            }
            $this->syncRows('audit_events', ['entity_type' => $kind, 'entity_id' => $row->id, 'stream' => $stream], 'position', $events);
        }
    }

    /** 保留未異動子列的主鍵，只寫新增／刪除／改變的項目；交易由呼叫者擁有。
     * @param  array<string, mixed>  $scope  可信的父列條件
     * @param  array<int, array<string, mixed>>  $rows  有序子列投影
     */
    private function syncRows(string $table, array $scope, string $key, array $rows): void
    {
        $query = DB::table($table)->where($scope);
        $existing = (clone $query)->get()->keyBy($key);
        $keys = array_column($rows, $key);
        $removed = $existing->keys()->diff($keys)->all();
        if ($removed !== []) {
            (clone $query)->whereIn($key, $removed)->delete();
        }
        $insert = [];
        foreach ($rows as $row) {
            $old = $existing->get($row[$key]);
            if ($old === null) {
                $insert[] = [...$scope, ...$row];

                continue;
            }
            $changes = [];
            foreach ($row as $column => $value) {
                $before = $old->{$column};
                $equal = $column === 'event_data'
                    ? $this->canonical(json_decode($before, true, flags: JSON_THROW_ON_ERROR)) === $this->canonical(json_decode($value, true, flags: JSON_THROW_ON_ERROR))
                    : ($before === null || $value === null ? $before === $value : (string) $before === (string) $value);
                if (! $equal) {
                    $changes[$column] = $value;
                }
            }
            if ($changes !== []) {
                (clone $query)->where($key, $row[$key])->update($changes);
            }
        }
        foreach (array_chunk($insert, 100) as $batch) {
            DB::table($table)->insert($batch);
        }
    }

    /** JSON物件鍵順序不影響歷史內容，陣列順序與型別仍需保留。 */
    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonical($item), $value);
    }
}
