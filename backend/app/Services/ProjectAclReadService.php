<?php

namespace App\Services;

use App\Data\WorkspaceContext;
use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Data\PageQueryData;
use App\Data\ProjectAclHistoryData;
use App\Data\ProjectMemberSummaryData;
use App\Models\WorkspaceMember;
use App\Repositories\ProjectAclReadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 授權後分責取得Project ACL；角色僅解析permission，不作放行捷徑。 */
class ProjectAclReadService
{
    /**
     * 注入單Project資格與有界管理資料入口。
     *
     * @param  ProjectReadService  $projects  當次Project授權
     * @param  ProjectAclReadRepository  $acl  成員／歷史資料投影
     */
    public function __construct(private ProjectReadService $projects, private ProjectAclReadRepository $acl) {}

    /**
     * 只提供可授予角色交集及當次ACL版本。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  專案公開ID
     * @return array<string,mixed> 管理metadata，不含成員或歷史
     */
    public function metadata(WorkspaceContext $context, string $projectId): array
    {
        $access = $this->projects->authorized($context, $projectId, 'project.members.manage');

        return ['projectId' => $projectId, 'revision' => $this->acl->revision($access->project),
            'roles' => array_values(array_filter(ProjectAccess::roles(), fn ($role) => ! array_diff($role['permissions'], $access->permissions)))];
    }

    /**
     * 當頁指派已批次預載，逐筆能力計算不產生SQL。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  專案公開ID
     * @param  PageQueryData  $filters  有界分頁／搜尋
     * @return LengthAwarePaginator<ProjectMemberSummaryData> 有效成員分頁
     */
    public function index(WorkspaceContext $context, string $projectId, PageQueryData $filters): LengthAwarePaginator
    {
        $access = $this->projects->authorized($context, $projectId, 'project.members.manage');
        $activeRoles = array_values(array_filter(['owner', 'manager', 'admin', 'user', 'member', 'maintainer', 'developer'], fn ($role) => in_array('workspace.read', WorkspacePermissions::resolve($role), true)));
        $workspaceRoles = array_values(array_filter(['owner', 'manager', 'admin', 'user', 'member', 'maintainer', 'developer'], fn ($role) => in_array('project.readAny', WorkspacePermissions::resolve($role), true)));
        $projectRoles = array_column(array_filter(ProjectAccess::roles(), fn ($role) => in_array('project.read', $role['permissions'], true)), 'id');

        return $this->acl->page($access->project, $filters, $workspaceRoles, $projectRoles, $activeRoles)->through(function (WorkspaceMember $member) use ($workspaceRoles, $access) {
            $inherited = in_array($member->role, $workspaceRoles, true);
            $assignment = $member->getRelation('assignments')->first();
            $roleId = $assignment?->role_id;
            $canChange = ! $inherited && $roleId !== null && ! array_diff(ProjectAccess::rolePermissions($roleId), $access->permissions);

            return new ProjectMemberSummaryData($member->user_id, $member->member_name, $member->member_email, $inherited ? 'workspace' : 'project', $roleId, $canChange);
        });
    }

    /**
     * 候選只在管理用途授權後提供信箱供同名辨識。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  專案公開ID
     * @param  PageQueryData  $filters  有界搜尋／分頁
     * @return LengthAwarePaginator<WorkspaceMember> 有效候選
     */
    public function candidates(WorkspaceContext $context, string $projectId, PageQueryData $filters): LengthAwarePaginator
    {
        $project = $this->projects->require($context, $projectId, 'project.members.manage');

        return $this->acl->candidates($project, $filters);
    }

    /**
     * 每次展開歷史均重驗Project管理資格。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  專案公開ID
     * @param  PageQueryData  $filters  有界歷史分頁
     * @return LengthAwarePaginator<ProjectAclHistoryData> 授權歷史
     */
    public function history(WorkspaceContext $context, string $projectId, PageQueryData $filters): LengthAwarePaginator
    {
        $project = $this->projects->require($context, $projectId, 'project.members.manage');

        $page = $this->acl->history($project, $filters);
        $userIds = array_values(array_unique(array_filter(array_map(fn ($event) => $event->event_data['userId'] ?? null, $page->items()))));
        $names = $this->acl->historyTargets($project, $userIds)->mapWithKeys(fn ($member) => [$member->user_id => $member->getRelation('user')->name]);

        return $page->through(fn ($event) => new ProjectAclHistoryData((string) $event->id, $event->event_data, $names[$event->event_data['userId'] ?? ''] ?? null));
    }
}
