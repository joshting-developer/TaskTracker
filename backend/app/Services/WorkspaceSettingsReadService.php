<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\PageQueryData;
use App\Data\WorkspaceMemberSummaryData;
use App\Repositories\SettingsRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 工作區一般設定、成員與角色選項分責；HTTP入口限制不取代API授權。 */
class WorkspaceSettingsReadService
{
    /**
     * 注入無租戶狀態的設定投影入口。
     *
     * @param  SettingsRepository  $settings  設定及有界成員資料入口
     */
    public function __construct(private SettingsRepository $settings) {}

    /**
     * 只有Workspace設定屬性，不附任何成員或角色全集。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @return array{workspace:array{id:string,name:string,revision:int}} 最小設定
     */
    public function show(array $actor): array
    {
        WorkspacePermissions::authorize($actor, 'workspace.read');
        $workspace = $this->settings->summary($actor['workspace']['id']);

        return ['workspace' => ['id' => $workspace->public_id, 'name' => $workspace->name, 'revision' => (int) $workspace->settings_revision]];
    }

    /**
     * 成員目錄只授予有效workspace.members.read，canChange沿用寫入規則。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  PageQueryData  $filters  有界分頁／搜尋
     * @return LengthAwarePaginator<WorkspaceMemberSummaryData> 成員管理分頁
     */
    public function members(array $actor, PageQueryData $filters): LengthAwarePaginator
    {
        WorkspacePermissions::authorize($actor, 'workspace.members.read');
        $workspace = $this->settings->summary($actor['workspace']['id']);

        return $this->settings->memberPage($workspace, $filters)->through(fn ($member) => new WorkspaceMemberSummaryData(
            $member->user_id, $member->member_name, $member->member_email, $member->role, (int) $member->role_revision,
            WorkspacePermissions::canManageRole($actor['permissions'], $member->role)));
    }

    /**
     * 只回當次可授予的非ownership角色，不讓前端自行猜授權。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @return array{roles:array<int,array{id:string,label:string}>} 可指派固定角色
     */
    public function roles(array $actor): array
    {
        WorkspacePermissions::authorize($actor, 'workspace.members.manage');
        $roles = array_filter(WorkspacePermissions::roles(), fn ($role) => ! in_array('workspace.ownership', $role['permissions'], true) && ! array_diff($role['permissions'], $actor['permissions']));

        return ['roles' => array_values(array_map(fn ($role) => ['id' => $role['id'], 'label' => $role['label']], $roles))];
    }
}
