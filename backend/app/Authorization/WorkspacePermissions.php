<?php

namespace App\Authorization;

use App\Domain\DomainError;

// 遷移階段保留原型共享專案行為, Project ACL 與新預設矩陣另階段實作
class WorkspacePermissions
{
    private const MEMBER = ['project.read', 'project.create', 'issue.write', 'timeEntry.writeOwn', 'board.manage'];
    private const MANAGE = ['workspace.invite', 'project.update', 'project.statuses.update', 'notification.readTeam', 'smtp.verify'];

    public static function resolve(string $roleId): array
    {
        return match ($roleId) {
            'owner', 'admin' => [...self::MEMBER, ...self::MANAGE],
            'manager' => array_values(array_diff([...self::MEMBER, ...self::MANAGE], ['smtp.verify'])),
            'maintainer' => ['project.read', 'project.update', 'project.statuses.update', 'issue.write', 'timeEntry.writeOwn', 'board.manage'],
            'developer' => ['project.read', 'issue.write', 'timeEntry.writeOwn'],
            'user', 'member' => self::MEMBER,
            default => [],
        };
    }

    public static function authorize(array $context, string $permission): void
    {
        if (!in_array($permission, $context['permissions'], true)) DomainError::fail('FORBIDDEN', '沒有執行此操作的權限');
    }
}
