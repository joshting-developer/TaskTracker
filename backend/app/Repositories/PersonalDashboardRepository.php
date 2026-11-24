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
     * @return LengthAwarePaginator<Issue> 當頁本人任務
     */
    public function page(string $workspaceId, string $userId, bool $readAny, array $readRoles, string $kind, string $today, string $until, PageQueryData $page): LengthAwarePaginator
    {
        $query = Issue::query()->whereHas('project', fn ($q) => $q
            ->whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->when(! $readAny, fn ($q) => $q->whereHas('members', fn ($q) => $q->whereNull('removed_at')->whereIn('role_id', $readRoles)
                ->whereHas('member', fn ($q) => $q->where('user_id', $userId)->whereNull('disabled_at')))))
            ->whereHas('assignee', fn ($q) => $q->where('user_id', $userId)->whereNull('disabled_at'))
            ->with(['project:id,public_id,name', 'status:id,code,label']);
        if ($kind !== 'recent') {
            $query->whereHas('status', fn ($q) => $q->where('code', '!=', 'closed'));
        }
        if ($kind === 'due') {
            $query->whereNotNull('due_date')->where('due_date', '<=', $until)->orderBy('due_date');
        } elseif ($kind === 'starting') {
            $query->whereBetween('start_date', [$today, $until])->orderBy('start_date');
        } else {
            $query->orderByDesc('updated_at');
        }

        return $query->orderBy('public_id')->paginate($page->perPage,
            ['id', 'public_id', 'project_id', 'state_id', 'identifier', 'title', 'due_date', 'start_date', 'updated_at'], 'page', $page->page);
    }
}
