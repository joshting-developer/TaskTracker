<?php

namespace App\Authorization;

use App\Domain\DomainError;

class WorkspacePermissions
{
    public static function resolve(string $roleId): array
    {
        $manage = ['workspace.read', 'workspace.members.read', 'workspace.invite', 'project.create', ...array_values(ProjectAccess::ANY)];
        return match ($roleId) {
            'owner' => [...$manage, 'smtp.verify'],
            'manager', 'admin' => $manage,
            'user', 'member', 'maintainer', 'developer' => ['workspace.read'],
            default => [],
        };
    }

    public static function authorize(array $context, string $permission): void
    {
        if (!in_array($permission, $context['permissions'], true)) DomainError::fail('FORBIDDEN', '沒有執行此操作的權限');
    }
}
