<?php

namespace App\Repositories;

use App\Data\IssueChildrenScope;
use App\Database\DatabaseTimes;
use App\Domain\DomainError;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** 最小投影分批同步子任務Epic, 不讀取或重寫留言與舊歷史 */
class IssueChildrenRepository
{
    /**
     * 解析已由Service授權的同租戶／專案外鍵
     *
     * @param  string  $workspaceId  可信工作區公開ID
     * @param  string  $projectId  已授權專案公開ID
     * @param  string  $parentId  父任務公開ID
     * @param  string  $epicId  已驗證Epic公開ID或空字串
     * @return IssueChildrenScope 不攜帶業務內容的外鍵
     */
    public function scope(string $workspaceId, string $projectId, string $parentId, string $epicId): IssueChildrenScope
    {
        $row = DB::table('issues as parent')->join('projects', 'projects.id', '=', 'parent.project_id')
            ->join('workspaces', 'workspaces.id', '=', 'parent.workspace_id')
            ->leftJoin('issues as epic', fn ($join) => $join->on('epic.workspace_id', '=', 'parent.workspace_id')->on('epic.project_id', '=', 'parent.project_id')->where('epic.public_id', $epicId))
            ->where('workspaces.public_id', $workspaceId)->where('projects.public_id', $projectId)
            ->whereColumn('projects.workspace_id', 'parent.workspace_id')->where('parent.public_id', $parentId)
            ->first(['parent.workspace_id', 'parent.project_id', 'parent.id', 'epic.id as epic_id']);
        if (! $row || ($epicId !== '' && $row->epic_id === null)) {
            DomainError::fail('NOT_FOUND', '找不到同專案的父任務或Epic');
        }

        return new IssueChildrenScope((int) $row->workspace_id, (int) $row->project_id, (int) $row->id, $row->epic_id === null ? null : (int) $row->epic_id);
    }

    /**
     * 以主鍵逐批取得版本、原Epic與下一個歷史位置, 每批最多100張
     *
     * @param  IssueChildrenScope  $scope  同交易已解析的外鍵
     * @return \Generator<int,array<object>> 最小子任務列, 不含描述、留言或歷史JSON
     */
    public function batches(IssueChildrenScope $scope): \Generator
    {
        // 相關外鍵一併排序, 讓MySQL沿既有複合索引倒序取一筆, 不對舊歷史filesort
        $lastId = 0;
        do {
            $rows = $this->query($scope)->leftJoin('issues as epic', fn ($join) => $join->on('epic.id', '=', 'issues.epic_id')->on('epic.workspace_id', '=', 'issues.workspace_id')->on('epic.project_id', '=', 'issues.project_id'))
                ->where('issues.id', '>', $lastId)->orderBy('issues.id')->limit(100)
                ->select(['issues.id', 'issues.public_id', 'issues.revision', 'epic.public_id as epic_public_id'])
                ->selectSub(DB::table('audit_events')->where('workspace_id', $scope->workspaceId)->where('entity_type', 'issues')
                    ->where('stream', 'history')->whereColumn('entity_id', 'issues.id')->selectRaw('position + 1')->orderByDesc('entity_id')->orderByDesc('position')->limit(1), 'history_position')
                ->get();
            if ($rows->isEmpty()) {
                return;
            }
            yield $rows->all();
            $lastId = (int) $rows->last()->id;
        } while ($rows->count() === 100);
    }

    /**
     * 同批更新版本與Epic並追加Service產生的歷史, 呼叫者持有Workspace寫鎖
     *
     * @param  IssueChildrenScope  $scope  同租戶外鍵
     * @param  array<array{id:int,revision:int,position:int,event:array}>  $changes  已計算的實際異動
     * @param  string  $at  當次UTC ISO時間
     * @return int 成功更新張數, 不符由Service使整個交易回滾
     */
    public function apply(IssueChildrenScope $scope, array $changes, string $at): int
    {
        if ($changes === []) {
            return 0;
        }
        $count = $this->query($scope)->where(function ($query) use ($changes) {
            foreach ($changes as $change) {
                $query->orWhere(fn ($query) => $query->where('issues.id', $change['id'])->where('revision', $change['revision']));
            }
        })->update(['epic_id' => $scope->epicId, 'revision' => DB::raw('revision + 1'), 'updated_at' => DatabaseTimes::store($at)]);
        if ($count === count($changes)) {
            DB::table('audit_events')->insert(array_map(fn ($change) => [
                'workspace_id' => $scope->workspaceId, 'entity_type' => 'issues', 'entity_id' => $change['id'], 'stream' => 'history',
                'position' => $change['position'], 'event_data' => json_encode($change['event'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ], $changes));
        }

        return $count;
    }

    /**
     * 將每次讀寫限定於父任務的同租戶及專案
     *
     * @param  IssueChildrenScope  $scope  可信外鍵
     * @return Builder 尚未執行的限定查詢, 僅供Repository內部使用
     */
    private function query(IssueChildrenScope $scope): Builder
    {
        return DB::table('issues')->where('issues.workspace_id', $scope->workspaceId)->where('issues.project_id', $scope->projectId)->where('issues.parent_issue_id', $scope->parentId);
    }
}
