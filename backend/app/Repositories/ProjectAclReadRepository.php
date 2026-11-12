<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\ProjectAccessControl;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Project管理專用投影，與一般姓名選項分開；不還原RecordStore歷史。 */
class ProjectAclReadRepository
{
    /**
     * 只查單Project ACL版本，不讀授權歷史。
     *
     * @param  Project  $project  已授權專案
     * @return int 尚無ACL時為0
     */
    public function revision(Project $project): int
    {
        return (int) ProjectAccessControl::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->value('revision');
    }

    /**
     * 以SQL搜尋及穩定排序限定同Workspace有效成員。
     *
     * @param  Project  $project  已授權專案
     * @param  PageQueryData  $filters  有界搜尋／分頁
     * @return Builder<WorkspaceMember> 固定租戶的成員查詢
     */
    private function members(Project $project, PageQueryData $filters): Builder
    {
        $query = WorkspaceMember::query()->join('users as u', 'u.id', '=', 'workspace_members.user_id')
            ->where('workspace_members.workspace_id', $project->workspace_id)->whereNull('workspace_members.disabled_at');
        if ($filters->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';
            $query->where(fn ($q) => $q->whereRaw("LOWER(u.name) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(u.email) LIKE ? ESCAPE '!'", [$term]));
        }

        return $query->orderBy('u.name')->orderBy('u.id');
    }

    /**
     * 有效成員在SQL先過濾，只批次預載當頁同Project指派。
     *
     * @param  Project  $project  已授權專案
     * @param  PageQueryData  $filters  有界搜尋／分頁
     * @param  array<int,string>  $workspaceRoles  具project.readAny的映射角色
     * @param  array<int,string>  $projectRoles  具project.read的映射角色
     * @param  array<int,string>  $activeRoles  具workspace.read的映射角色
     * @return LengthAwarePaginator<WorkspaceMember> 當頁有效成員
     */
    public function page(Project $project, PageQueryData $filters, array $workspaceRoles, array $projectRoles, array $activeRoles): LengthAwarePaginator
    {
        return $this->members($project, $filters)->whereIn('workspace_members.role', $activeRoles)
            ->where(fn ($q) => $q->whereIn('workspace_members.role', $workspaceRoles)->orWhereHas('assignments', fn ($q) => $q->where('project_id', $project->id)->whereNull('removed_at')->whereIn('role_id', $projectRoles)))
            ->with(['assignments' => fn ($q) => $q->where('project_id', $project->id)->whereNull('removed_at')->select(['id', 'member_id', 'role_id'])])
            ->paginate($filters->perPage, ['workspace_members.id', 'workspace_members.user_id', 'workspace_members.role', 'u.name as member_name', 'u.email as member_email'], 'page', $filters->page);
    }

    /**
     * 搜尋可加入的有效Workspace成員，不讀Project資格及歷史全集。
     *
     * @param  Project  $project  已授權專案
     * @param  PageQueryData  $filters  有界搜尋／分頁
     * @return LengthAwarePaginator<WorkspaceMember> 管理用途候選
     */
    public function candidates(Project $project, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->members($project, $filters)->paginate($filters->perPage,
            ['workspace_members.id', 'workspace_members.user_id', 'u.name as member_name', 'u.email as member_email'], 'page', $filters->page);
    }

    /**
     * 只讀指定Project ACL的歷史分頁，依位置及主鍵穩定倒序。
     *
     * @param  Project  $project  已授權專案
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator<AuditEvent> 授權歷史
     */
    public function history(Project $project, PageQueryData $filters): LengthAwarePaginator
    {
        $acl = ProjectAccessControl::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->select('id');

        return AuditEvent::where('workspace_id', $project->workspace_id)->where('entity_type', 'projectAcls')->whereIn('entity_id', $acl)->where('stream', 'history')
            ->orderByDesc('position')->orderByDesc('id')->paginate($filters->perPage, ['id', 'event_data'], 'page', $filters->page);
    }

    /**
     * 只批次查詢當頁歷史提到的成員名稱，不取候選全集。
     *
     * @param  Project  $project  已授權專案
     * @param  array<int,string>  $userIds  當頁最多100個目標
     * @return Collection<int,WorkspaceMember> 歷史目標名稱
     */
    public function historyTargets(Project $project, array $userIds): Collection
    {
        return WorkspaceMember::where('workspace_id', $project->workspace_id)->whereIn('user_id', $userIds)->with('user:id,name')->limit(100)->get(['id', 'user_id']);
    }
}
