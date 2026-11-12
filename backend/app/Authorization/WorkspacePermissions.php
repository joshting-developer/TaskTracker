<?php

namespace App\Authorization;

use App\Domain\DomainError;

/**
 * 將工作空間角色映射成permission並提供集中驗證。
 */
class WorkspacePermissions
{
    /**
     * 將角色ID轉成固定工作空間permission集合。
     *
     * @param  string  $roleId  僅用來查權限映射的角色ID
     * @return array
     */
    public static function resolve(string $roleId): array
    {
        $personal = ['workspace.read', 'todo.read', 'todo.writeOwn', 'todo.assign'];
        $manage = [...$personal, 'workspace.timeReports.read', 'workspace.members.read', 'workspace.members.manage', 'workspace.update', 'workspace.invite', 'workspace.operationLogs.read', 'project.create', ...array_values(ProjectAccess::ANY)];

        return match ($roleId) {
            'owner' => [...$manage, 'workspace.ownership', 'smtp.verify'],
            'manager', 'admin' => $manage,
            'user', 'member', 'maintainer', 'developer' => $personal,
            default => [],
        };
    }

    /**
     * 檢查有效permission，拒絕未授權操作。
     *
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @param  string  $permission  需要具備的permission ID
     * @return void
     */
    public static function authorize(array $workspaceSession, string $permission): void
    {
        if (! in_array($permission, $workspaceSession['permissions'], true)) {
            DomainError::fail('FORBIDDEN', '沒有執行此操作的權限');
        }
    }

    /**
     * 固定角色的顯示與permission映射，供設定讀寫共用。
     *
     * @return array<int,array{id:string,label:string,permissions:array<int,string>}> 固定角色定義
     */
    public static function roles(): array
    {
        return array_map(fn ($id, $label) => ['id' => $id, 'label' => $label, 'permissions' => self::resolve($id)],
            ['owner', 'manager', 'user'], ['擁有者', '管理者', '一般成員']);
    }

    /**
     * 以有效權限交集及ownership保留條件判斷可否管理目前角色。
     *
     * @param  array<int,string>  $permissions  操作者當次有效權限
     * @param  string  $roleId  只用於解析目標permission的角色ID
     * @return bool 是否可調整目標現有資格
     */
    public static function canManageRole(array $permissions, string $roleId): bool
    {
        $current = self::resolve($roleId);

        return in_array('workspace.members.manage', $permissions, true)
            && ! in_array('workspace.ownership', $current, true) && ! array_diff($current, $permissions);
    }
}
