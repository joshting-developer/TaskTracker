<?php

namespace App\Authorization;

use App\Domain\DomainError;

class WorkspacePermissions
{
    public static function resolve(string $roleId): array
    {
        $personal = ['workspace.read', 'todo.read', 'todo.writeOwn', 'todo.assign'];
        $manage = [...$personal, 'workspace.members.read', 'workspace.invite', 'project.create', ...array_values(ProjectAccess::ANY)];
        return match ($roleId) {
            'owner' => [...$manage, 'smtp.verify'],
            'manager', 'admin' => $manage,
            'user', 'member', 'maintainer', 'developer' => $personal,
            default => [],
        };
    }

    public static function authorize(array $context, string $permission): void
    {
        if (!in_array($permission, $context['permissions'], true)) DomainError::fail('FORBIDDEN', '沒有執行此操作的權限');
    }
}
