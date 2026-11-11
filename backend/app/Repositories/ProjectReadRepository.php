<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/** 專案讀取投影；呼叫者提供已驗證工作區與有效公開ID集合。 */
class ProjectReadRepository
{
    /** 只供當次授權組合使用，不載入專案內容或歷史。
     * @return Collection<int, Project>
     */
    public function references(string $workspaceId): Collection
    {
        return Project::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))->get(['id', 'workspace_id', 'public_id']);
    }

    /** 單人讀取資格只查本人的Project指派，不載入整個工作區成員配置。
     * @return Collection<int, ProjectMember>
     */
    public function assignments(string $workspaceId, string $userId): Collection
    {
        return ProjectMember::whereHas('project.workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->whereHas('member', fn ($q) => $q->where('user_id', $userId))
            ->with(['project:id,public_id', 'member:id,user_id'])
            ->get(['id', 'public_id', 'project_id', 'member_id', 'role_id', 'removed_at']);
    }

    /** 由SQL執行搜尋與分頁，不讓未授權Project進入回傳集合。 */
    public function page(string $workspaceId, array $ids, PageQueryData $filters): LengthAwarePaginator
    {
        return Project::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->whereIn('public_id', $ids)->with(['lead:id,user_id', 'lead.user:id,name'])
            ->when($filters->search !== '', fn ($q) => $q->where(function ($q) use ($filters) {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';
                $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term]);
            }))->orderByDesc('updated_at')->orderBy('public_id')
            ->paginate($filters->perPage, ['id', 'workspace_id', 'public_id', 'name', 'code', 'description', 'color', 'lead_id', 'revision', 'created_at', 'updated_at'], 'page', $filters->page);
    }

    /** 讀取同工作區指定專案及此頁需要的關聯。 */
    public function find(string $workspaceId, string $id): ?Project
    {
        return Project::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->where('public_id', $id)->with(['lead:id,user_id', 'lead.user:id,name', 'statuses'])->first();
    }
}
