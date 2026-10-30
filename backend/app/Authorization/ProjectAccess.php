<?php

namespace App\Authorization;

use App\Domain\DomainError;
use App\Repositories\RecordStore;

class ProjectAccess
{
    public const READ = ['project.read', 'issue.read'];
    public const DEVELOP = [...self::READ, 'issue.write', 'timeEntry.readOwn', 'timeEntry.readTeam', 'timeEntry.writeOwn'];
    public const MANAGE = [...self::DEVELOP, 'project.update', 'project.statuses.update', 'project.members.manage', 'board.manage', 'notification.readTeam'];
    public const ANY = [
        'project.read' => 'project.readAny', 'issue.read' => 'issue.readAny', 'issue.write' => 'issue.writeAny',
        'project.update' => 'project.updateAny', 'project.statuses.update' => 'project.statuses.updateAny',
        'project.members.manage' => 'project.members.manageAny', 'board.manage' => 'board.manageAny',
        'timeEntry.readOwn' => 'timeEntry.readOwnAny', 'timeEntry.readTeam' => 'timeEntry.readTeamAny',
        'timeEntry.writeOwn' => 'timeEntry.writeOwnAny', 'notification.readTeam' => 'notification.readTeamAny',
    ];

    public function __construct(private RecordStore $store, private array $context) {}

    public static function roles(): array
    {
        return [
            ['id' => 'lead', 'label' => '負責人', 'permissions' => self::MANAGE],
            ['id' => 'maintainer', 'label' => '專案管理者', 'permissions' => self::MANAGE],
            ['id' => 'developer', 'label' => '開發者', 'permissions' => self::DEVELOP],
            ['id' => 'reader', 'label' => '讀者', 'permissions' => self::READ],
        ];
    }

    public static function rolePermissions(string $id): array
    {
        foreach (self::roles() as $role) if ($role['id'] === $id) return $role['permissions'];
        return [];
    }

    public static function memberId(string $projectId, string $userId): string { return $projectId.':'.$userId; }

    public function permissions(string $projectId): array
    {
        $member = $this->store->get('members', $this->context['user']['id']);
        if (!in_array('workspace.read', $this->context['permissions'], true) || !$member || !empty($member['disabledAt']) || !$this->store->get('projects', $projectId)) return [];
        $assignment = $this->store->get('projectMembers', self::memberId($projectId, $member['id']));
        $permissions = $assignment && empty($assignment['removedAt']) && $assignment['projectId'] === $projectId && $assignment['userId'] === $member['id'] ? self::rolePermissions($assignment['roleId']) : [];
        foreach (self::ANY as $permission => $any) if (in_array($any, $this->context['permissions'], true)) $permissions[] = $permission;
        return array_values(array_unique($permissions));
    }

    public function can(string $projectId, string $permission): bool
    {
        if (!array_key_exists($permission, self::ANY)) return false;
        $permissions = $this->permissions($projectId);
        return in_array('project.read', $permissions, true) && in_array($permission, $permissions, true);
    }

    public function require(string $projectId, string $permission): array
    {
        $project = $this->store->get('projects', $projectId);
        if (!$project || !$this->can($projectId, 'project.read')) DomainError::fail('NOT_FOUND', '找不到或無權存取這個專案');
        if (!$this->can($projectId, $permission)) DomainError::fail('FORBIDDEN', '沒有執行此操作的權限');
        return $project;
    }

    public function forUser(string $userId): self
    {
        $member = $this->store->get('members', $userId);
        return new self($this->store, ['user' => ['id' => $userId], 'permissions' => WorkspacePermissions::resolve($member['role'] ?? '')]);
    }

    public function eligibleMembers(string $projectId): array
    {
        return array_values(array_filter($this->store->all('members'), fn ($member) => empty($member['disabledAt']) && $this->forUser($member['id'])->can($projectId, 'project.read')));
    }

    public function source(string $projectId): string
    {
        return in_array('project.readAny', $this->context['permissions'], true) ? 'workspace' : 'project';
    }
}
