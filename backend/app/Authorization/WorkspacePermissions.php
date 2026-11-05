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
        $manage = [...$personal, 'workspace.timeReports.read', 'workspace.members.read', 'workspace.members.manage', 'workspace.update', 'workspace.invite', 'project.create', ...array_values(ProjectAccess::ANY)];

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
}
