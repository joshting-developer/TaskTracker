<?php

namespace App\Repositories;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Data\PageQueryData;
use App\Models\Project;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** 僅投影有效Project成員識別及名稱，不帶email或ACL歷史。 */
class ProjectMemberReadRepository
{
    /**
     * 依有效permission映射與Project指派在SQL篩選最小成員選項。
     *
     * @param  Project  $project  已授權Project
     * @param  PageQueryData  $filters  有界搜尋及分頁
     * @return LengthAwarePaginator<WorkspaceMember> 有效成員姓名選項
     */
    public function page(Project $project, PageQueryData $filters): LengthAwarePaginator
    {
        $workspaceRoles = array_values(array_filter(['owner', 'manager', 'admin', 'user', 'member'], fn ($r) => in_array('project.readAny', WorkspacePermissions::resolve($r), true)));
        $projectRoles = array_column(array_filter(ProjectAccess::roles(), fn ($r) => in_array('project.read', $r['permissions'], true)), 'id');
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';

        return WorkspaceMember::where('workspace_id', $project->workspace_id)->whereNull('disabled_at')
            ->where(fn ($q) => $q->whereIn('role', $workspaceRoles)->orWhereHas('assignments', fn ($q) => $q->where('project_id', $project->id)->whereNull('removed_at')->whereIn('role_id', $projectRoles)))
            ->whereHas('user', fn ($q) => $q->when($filters->search !== '', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])))
            ->with('user:id,name')->orderBy('user_id')->paginate($filters->perPage, ['id', 'user_id'], 'page', $filters->page);
    }

    /**
     * 供已通過建立／編輯Project資格的負責人選擇器查詢有效Workspace成員。
     *
     * @param  string  $workspaceId  可信Workspace公開ID
     * @param  array<int,string>  $roles  由project.readAny映射取得的新Project可見資格
     * @param  PageQueryData  $filters  有界搜尋與分頁
     * @return LengthAwarePaginator 有效成員最小選項
     */
    public function leads(string $workspaceId, array $roles, PageQueryData $filters): LengthAwarePaginator
    {
        $q = DB::table('workspace_members as m')->join('workspaces as w', 'w.id', '=', 'm.workspace_id')->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('w.public_id', $workspaceId)->whereNull('m.disabled_at')->whereIn('m.role', $roles);
        if ($filters->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';
            $q->whereRaw("LOWER(u.name) LIKE ? ESCAPE '!'", [$term]);
        }

        return $q->orderBy('u.name')->orderBy('u.id')->paginate($filters->perPage, ['u.id', 'u.name'], 'page', $filters->page);
    }
}
