<?php

namespace App\Authorization;

use App\Domain\DomainError;
use App\Repositories\RecordStore;

/**
 * 依有效permission與Project資格決定資料存取，角色只提供映射。
 */
class ProjectAccess
{
    public const READ = ['project.read', 'issue.read'];

    public const DEVELOP = [...self::READ, 'issue.write', 'timeEntry.readOwn', 'timeEntry.readTeam', 'timeEntry.writeOwn'];

    public const MANAGE = [...self::DEVELOP, 'project.update', 'project.statuses.update', 'project.members.manage', 'board.manage', 'notification.readTeam'];

    public const ANY = [
        'project.read' => 'project.readAny',
        'issue.read' => 'issue.readAny',
        'issue.write' => 'issue.writeAny',
        'project.update' => 'project.updateAny',
        'project.statuses.update' => 'project.statuses.updateAny',
        'project.members.manage' => 'project.members.manageAny',
        'board.manage' => 'board.manageAny',
        'timeEntry.readOwn' => 'timeEntry.readOwnAny',
        'timeEntry.readTeam' => 'timeEntry.readTeamAny',
        'timeEntry.writeOwn' => 'timeEntry.writeOwnAny',
        'notification.readTeam' => 'notification.readTeamAny',
    ];

    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  RecordStore  $store  限定工作空間的資料存取入口
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @return void
     */
    public function __construct(private RecordStore $store, private array $workspaceSession) {}

    /**
     * 列出固定專案角色及對應permission集合。
     *
     * @return array
     */
    public static function roles(): array
    {
        return [
            [
                'id' => 'lead',
                'label' => '負責人',
                'permissions' => self::MANAGE,
            ],
            [
                'id' => 'maintainer',
                'label' => '專案管理者',
                'permissions' => self::MANAGE,
            ],
            [
                'id' => 'developer',
                'label' => '開發者',
                'permissions' => self::DEVELOP,
            ],
            [
                'id' => 'reader',
                'label' => '讀者',
                'permissions' => self::READ,
            ],
        ];
    }

    /**
     * 僅依角色ID取得權限映射，不直接放行操作。
     *
     * @param  string  $id  紀錄的固定識別碼
     * @return array
     */
    public static function rolePermissions(string $id): array
    {
        foreach (self::roles() as $role) {
            if ($role['id'] === $id) {
                return $role['permissions'];
            }
        }

        return [];
    }

    /**
     * 組合專案與使用者的成員資格識別碼。
     *
     * @param  string  $projectId  所屬專案識別碼
     * @param  string  $userId  操作者或目標成員識別碼
     * @return string
     */
    public static function memberId(string $projectId, string $userId): string
    {
        return $projectId.':'.$userId;
    }

    /**
     * 彙整有效Project資格及Workspace繼承權限。
     *
     * @param  string  $projectId  所屬專案識別碼
     * @return array
     */
    public function permissions(string $projectId): array
    {
        $member = $this->store->get('members', $this->workspaceSession['user']['id']);
        $canEnterWorkspace = in_array('workspace.read', $this->workspaceSession['permissions'], true);
        $isActiveMember = $member && empty($member['disabledAt']);
        if (! $canEnterWorkspace || ! $isActiveMember || ! $this->store->get('projects', $projectId)) {
            return [];
        }
        $assignment = $this->store->get('projectMembers', self::memberId($projectId, $member['id']));
        $hasActiveAssignment = $assignment && empty($assignment['removedAt']) && $assignment['projectId'] === $projectId && $assignment['userId'] === $member['id'];
        $permissions = $hasActiveAssignment ? self::rolePermissions($assignment['roleId']) : [];
        foreach (self::ANY as $permission => $any) {
            if (in_array($any, $this->workspaceSession['permissions'], true)) {
                $permissions[] = $permission;
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * 確認具專案讀取資格且持有所需permission。
     *
     * @param  string  $projectId  所屬專案識別碼
     * @param  string  $permission  需要具備的permission ID
     * @return bool
     */
    public function can(string $projectId, string $permission): bool
    {
        if (! array_key_exists($permission, self::ANY)) {
            return false;
        }
        $permissions = $this->permissions($projectId);

        return in_array('project.read', $permissions, true) && in_array($permission, $permissions, true);
    }

    /**
     * 取得有權操作的專案，隱藏不可見專案存在性。
     *
     * @param  string  $projectId  所屬專案識別碼
     * @param  string  $permission  需要具備的permission ID
     * @return array
     */
    public function require(string $projectId, string $permission): array
    {
        $project = $this->store->get('projects', $projectId);
        if (! $project || ! $this->can($projectId, 'project.read')) {
            DomainError::fail('NOT_FOUND', '找不到或無權存取這個專案');
        }
        if (! $this->can($projectId, $permission)) {
            DomainError::fail('FORBIDDEN', '沒有執行此操作的權限');
        }

        return $project;
    }

    /**
     * 為另一位成員建立獨立的權限判斷入口。
     *
     * @param  string  $userId  操作者或目標成員識別碼
     * @return self
     */
    public function forUser(string $userId): self
    {
        $member = $this->store->get('members', $userId);

        return new self($this->store, [
            'user' => ['id' => $userId],
            'permissions' => WorkspacePermissions::resolve($member['role'] ?? ''),
        ]);
    }

    /**
     * 列出有權讀取目前專案的有效成員。
     *
     * @param  string  $projectId  所屬專案識別碼
     * @return array
     */
    public function eligibleMembers(string $projectId): array
    {
        return array_values(array_filter($this->store->all('members'), fn ($member) => empty($member['disabledAt']) && $this->forUser($member['id'])->can($projectId, 'project.read')));
    }

    /**
     * 判斷專案讀取能力來自工作空間或個別Project。
     *
     * @param  string  $projectId  所屬專案識別碼
     * @return string
     */
    public function source(string $projectId): string
    {
        return in_array('project.readAny', $this->workspaceSession['permissions'], true) ? 'workspace' : 'project';
    }
}
