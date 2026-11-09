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
            DB::table('issue_comments')->where('issue_id', $row->id)->delete();
            foreach ($value['comments'] ?? [] as $position => $comment) {
                DB::table('issue_comments')->insert([
                    'issue_id' => $row->id,
                    'public_id' => $comment['id'],
                    'content' => $comment['content'],
                    'author' => $comment['author'],
                    'created_at' => DatabaseTimes::store($comment['createdAt'] ?? null),
                    'position' => $position,
                ]);
            }
            DB::table('issue_relations')->where('issue_id', $row->id)->delete();
            foreach ($value['relatedIssueIds'] ?? [] as $position => $id) {
                DB::table('issue_relations')->insert([
                    'workspace_id' => $row->workspace_id,
                    'project_id' => $row->project_id,
                    'issue_id' => $row->id,
                    'related_issue_id' => $records->internalId('issues', $id),
                    'position' => $position,
                ]);
            }
        }
        if ($kind === 'boards') {
            DB::table('board_issues')->where('board_id', $row->id)->delete();
            foreach ($value['selectedIssueIds'] ?? [] as $position => $id) {
                DB::table('board_issues')->insert([
                    'workspace_id' => $row->workspace_id,
                    'project_id' => $row->project_id,
                    'board_id' => $row->id,
                    'issue_id' => $records->internalId('issues', $id),
                    'position' => $position,
                ]);
            }
        }
        foreach (['history', 'settingsHistory', 'detailsHistory'] as $stream) {
            if (! array_key_exists($stream, $value)) {
                continue;
            }
            DB::table('audit_events')->where('entity_type', $kind)->where('entity_id', $row->id)->where('stream', $stream)->delete();
            foreach ($value[$stream] as $position => $event) {
                DB::table('audit_events')->insert([
                    'workspace_id' => $row->workspace_id,
                    'entity_type' => $kind,
                    'entity_id' => $row->id,
                    'stream' => $stream,
                    'position' => $position,
                    'event_data' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }
        }
    }
}
