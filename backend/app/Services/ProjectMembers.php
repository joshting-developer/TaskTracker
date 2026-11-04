<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;

/**
 * 管理Project成員與角色權限組合，保留ACL版本與授權歷史。
 */
class ProjectMembers
{
    private ProjectAccess $access;

    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  RecordStore  $store  限定工作空間的資料存取入口
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @return void
     */
    public function __construct(private RecordStore $store, private array $workspaceSession)
    {
        $this->access = new ProjectAccess($store, $workspaceSession);
    }

    /**
     * 整理有效成員、可授予角色與ACL歷史。
     *
     * @param  string  $projectId  所屬專案識別碼
     * @return array
     */
    public function listing(string $projectId): array
    {
        $this->access->require($projectId, 'project.members.manage');
        $acl = $this->store->get('projectAcls', $projectId) ?? [
            'revision' => 0,
            'history' => [],
        ];
        $members = [];
        foreach ($this->access->eligibleMembers($projectId) as $member) {
            $assignment = $this->store->get('projectMembers', ProjectAccess::memberId($projectId, $member['id']));
            $inherited = $this->access->forUser($member['id'])->source($projectId) === 'workspace';
            $members[] = [
                'userId' => $member['id'],
                'name' => $member['name'],
                'email' => $member['email'],
                'source' => $inherited ? 'workspace' : 'project',
                'roleId' => $assignment && empty($assignment['removedAt']) ? $assignment['roleId'] : null,
            ];
        }
        $effective = $this->access->permissions($projectId);

        return [
            'projectId' => $projectId,
            'revision' => $acl['revision'],
            'members' => $members,
            'candidates' => array_values(array_filter($this->store->all('members'), fn ($m) => empty($m['disabledAt']))),
            'roles' => array_values(array_filter(ProjectAccess::roles(), fn ($r) => ! array_diff($r['permissions'], $effective))),
            'history' => $acl['history'],
        ];
    }

    /**
     * 驗證可授予權限及ACL版本後異動專案資格。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @param  bool  $remove  是否撤回專案資格
     * @return array
     */
    public function change(array $input, bool $remove): array
    {
        $projectId = Fields::text($input['projectId'] ?? null, '專案', 100, true);
        $this->access->require($projectId, 'project.members.manage');
        $acl = $this->store->get('projectAcls', $projectId) ?? [
            'id' => $projectId,
            'revision' => 0,
            'history' => [],
        ];
        if (($input['expectedRevision'] ?? null) !== $acl['revision']) {
            DomainError::fail('REVISION_CONFLICT', '專案成員已被更新, 請重新載入後再修改');
        }
        $userId = Fields::text($input['userId'] ?? null, '成員', 100, true);
        $member = $this->store->get('members', $userId);
        if (! $member || ! empty($member['disabledAt'])) {
            DomainError::fail('INVALID_INPUT', '請選擇有效的工作空間成員');
        }
        $id = ProjectAccess::memberId($projectId, $userId);
        $current = $this->store->get('projectMembers', $id);
        $roleId = $remove ? null : Fields::text($input['roleId'] ?? null, '專案角色', 100, true);
        $grant = $roleId === null ? [] : ProjectAccess::rolePermissions($roleId);
        if (! $remove && ! $grant) {
            DomainError::fail('INVALID_INPUT', '請選擇有效的專案角色');
        }
        // 指派與撤回都不能處理超出自己有效授權的角色, 不依角色名稱或高低排序
        $currentGrant = $current && empty($current['removedAt']) ? ProjectAccess::rolePermissions($current['roleId']) : [];
        if (array_diff([...$grant, ...$currentGrant], $this->access->permissions($projectId))) {
            DomainError::fail('FORBIDDEN', '不能指派或調整超出自己有效權限的專案角色');
        }
        if ($remove && (! $current || ! empty($current['removedAt']))) {
            DomainError::fail('NOT_FOUND', '找不到這筆專案成員資格');
        }
        $at = now()->utc()->toISOString();
        $this->store->put('projectMembers', $id, [
            'id' => $id,
            'projectId' => $projectId,
            'userId' => $userId,
            'roleId' => $roleId ?? $current['roleId'],
            'revision' => ($current['revision'] ?? 0) + 1,
            'removedAt' => $remove ? $at : null,
            'updatedAt' => $at,
        ]);
        $this->store->put('projectAcls', $projectId, [
            ...$acl,
            'revision' => $acl['revision'] + 1,
            'history' => [
                ...$acl['history'],
                [
                    'actorId' => $this->workspaceSession['user']['id'],
                    'actor' => $this->workspaceSession['user']['name'],
                    'userId' => $userId,
                    'before' => $current && empty($current['removedAt']) ? $current['roleId'] : null,
                    'after' => $roleId,
                    'at' => $at,
                ],
            ],
        ]);

        return [
            'projectId' => $projectId,
            'revision' => $acl['revision'] + 1,
        ];
    }
}
