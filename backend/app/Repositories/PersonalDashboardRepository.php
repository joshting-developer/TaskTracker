<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Models\Issue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 本人工作摘要的SQL投影, 不載入租戶快照、描述或活動原文。 */
class PersonalDashboardRepository
{
    /**
     * 先以租戶、有效專案資格與本人指派篩選, 再依種類穩定排序及分頁。
     *
     * @param  string  $workspaceId  可信工作區公開ID
     * @param  string  $userId  可信操作者ID
     * @param  bool  $readAny  是否具issue.readAny
     * @param  array<int,string>  $readRoles  由permission映射的專案角色
     * @param  string  $kind  已驗證摘要種類
     * @param  string  $today  台北工作日
     * @param  string  $until  七天後工作日
     * @param  PageQueryData  $page  有界分頁
     * @param  array{projectId?:string,state?:string,priority?:string,assignee?:string,tagId?:string,dueFrom?:string,dueTo?:string}  $filters  已驗證的小工具條件
     * @return LengthAwarePaginator<Issue> 當頁本人任務
     */
    public function page(string $workspaceId, string $userId, bool $readAny, array $readRoles, string $kind, string $today, string $until, PageQueryData $page, array $filters = []): LengthAwarePaginator
    {
        $query = Issue::query()->whereHas('project', fn ($q) => $q
            ->whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->when(! $readAny, fn ($q) => $q->whereHas('members', fn ($q) => $q->whereNull('removed_at')->whereIn('role_id', $readRoles)
                ->whereHas('member', fn ($q) => $q->where('user_id', $userId)->whereNull('disabled_at')))))
            ->when(($filters['assignee'] ?? 'mine') !== 'all', fn ($q) => ($filters['assignee'] ?? '') === 'unassigned' ? $q->whereNull('assignee_id') : $q->whereHas('assignee', fn ($q) => $q->where('user_id', ($filters['assignee'] ?? 'mine') === 'mine' ? $userId : $filters['assignee'])->whereNull('disabled_at')))
            ->with(['project:id,public_id,name', 'status:id,code,label']);
        if (! empty($filters['projectId'])) {
            $query->whereHas('project', fn ($q) => $q->where('public_id', $filters['projectId']));
        }
        if (! empty($filters['state'])) {
            $query->whereHas('status', fn ($q) => $q->where('code', $filters['state']));
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['tagId'])) {
            $query->whereHas('tags', fn ($q) => $q->where('public_id', $filters['tagId']));
        }
        if (! empty($filters['dueFrom'])) {
            $query->where('due_date', '>=', $filters['dueFrom']);
        }
        if (! empty($filters['dueTo'])) {
            $query->where('due_date', '<=', $filters['dueTo']);
        }
        if (! isset($filters['state']) && ! in_array($kind, ['recent', 'tasks'], true)) {
            $query->whereHas('status', fn ($q) => $q->where('code', '!=', 'closed'));
        }
        if ($kind === 'due') {
            $query->whereNotNull('due_date')->where('due_date', '<=', $until)->orderBy('due_date');
        } elseif ($kind === 'starting') {
            $query->whereBetween('start_date', [$today, $until])->orderBy('start_date');
        } else {
            $query->orderByDesc('updated_at');
        }

        return $query->orderBy('id')->paginate($page->perPage,
            ['id', 'public_id', 'project_id', 'state_id', 'identifier', 'title', 'due_date', 'start_date', 'updated_at'], 'page', $page->page);
    }
}
