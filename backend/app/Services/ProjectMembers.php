<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;

class ProjectMembers
{
    private ProjectAccess $access;
    public function __construct(private RecordStore $store, private array $context) { $this->access = new ProjectAccess($store, $context); }

    public function listing(string $projectId): array
    {
        $this->access->require($projectId, 'project.members.manage');
        $acl = $this->store->get('projectAcls', $projectId) ?? ['revision' => 0, 'history' => []];
        $members = [];
        foreach ($this->access->eligibleMembers($projectId) as $member) {
            $assignment = $this->store->get('projectMembers', ProjectAccess::memberId($projectId, $member['id']));
            $inherited = $this->access->forUser($member['id'])->source($projectId) === 'workspace';
            $members[] = ['userId' => $member['id'], 'name' => $member['name'], 'email' => $member['email'], 'source' => $inherited ? 'workspace' : 'project', 'roleId' => $assignment && empty($assignment['removedAt']) ? $assignment['roleId'] : null];
        }
        $effective = $this->access->permissions($projectId);
        return ['projectId' => $projectId, 'revision' => $acl['revision'], 'members' => $members,
            'candidates' => array_values(array_filter($this->store->all('members'), fn ($m) => empty($m['disabledAt']))),
            'roles' => array_values(array_filter(ProjectAccess::roles(), fn ($r) => !array_diff($r['permissions'], $effective))), 'history' => $acl['history']];
    }

    public function change(array $input, bool $remove): array
    {
        $projectId = Fields::text($input['projectId'] ?? null, '專案', 100, true);
        $this->access->require($projectId, 'project.members.manage');
        $acl = $this->store->get('projectAcls', $projectId) ?? ['id' => $projectId, 'revision' => 0, 'history' => []];
        if (($input['expectedRevision'] ?? null) !== $acl['revision']) DomainError::fail('REVISION_CONFLICT', '專案成員已被更新, 請重新載入後再修改');
        $userId = Fields::text($input['userId'] ?? null, '成員', 100, true);
        $member = $this->store->get('members', $userId);
        if (!$member || !empty($member['disabledAt'])) DomainError::fail('INVALID_INPUT', '請選擇有效的工作空間成員');
        $id = ProjectAccess::memberId($projectId, $userId);
        $current = $this->store->get('projectMembers', $id);
        $roleId = $remove ? null : Fields::text($input['roleId'] ?? null, '專案角色', 100, true);
        $grant = $roleId === null ? [] : ProjectAccess::rolePermissions($roleId);
        if (!$remove && !$grant) DomainError::fail('INVALID_INPUT', '請選擇有效的專案角色');
        // 指派與撤回都不能處理超出自己有效授權的角色, 不依角色名稱或高低排序
        $currentGrant = $current && empty($current['removedAt']) ? ProjectAccess::rolePermissions($current['roleId']) : [];
        if (array_diff([...$grant, ...$currentGrant], $this->access->permissions($projectId))) DomainError::fail('FORBIDDEN', '不能指派或調整超出自己有效權限的專案角色');
        if ($remove && (!$current || !empty($current['removedAt']))) DomainError::fail('NOT_FOUND', '找不到這筆專案成員資格');
        $at = now()->utc()->toISOString();
        $this->store->put('projectMembers', $id, ['id' => $id, 'projectId' => $projectId, 'userId' => $userId, 'roleId' => $roleId ?? $current['roleId'], 'revision' => ($current['revision'] ?? 0) + 1, 'removedAt' => $remove ? $at : null, 'updatedAt' => $at]);
        $this->store->put('projectAcls', $projectId, [...$acl, 'revision' => $acl['revision'] + 1, 'history' => [...$acl['history'], ['actorId' => $this->context['user']['id'], 'actor' => $this->context['user']['name'], 'userId' => $userId, 'before' => $current && empty($current['removedAt']) ? $current['roleId'] : null, 'after' => $roleId, 'at' => $at]]]);
        return ['projectId' => $projectId, 'revision' => $acl['revision'] + 1];
    }
}
