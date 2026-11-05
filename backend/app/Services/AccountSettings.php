<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Models\WorkspaceMember;
use App\Repositories\RecordStore;
use App\Repositories\SettingsRepository;

/** 管理本人資料、工作空間名稱及固定角色指派；由ApiExecution提供交易。 */
class AccountSettings
{
    public const OPERATIONS = ['getProfile', 'updateProfile', 'getWorkspaceSettings', 'updateWorkspace', 'setWorkspaceMemberRole'];

    private SettingsRepository $repository;

    /** 建立可信任的設定服務。
     * @param  array  $context  後端Session重新取得的身分與permission
     * @return void
     */
    public function __construct(private array $context)
    {
        $this->repository = new SettingsRepository;
    }

    /** 授權後讀取設定或執行具重試保護的寫入。
     * @param  string  $operation  固定操作名稱
     * @param  array  $input  原始輸入
     * @return array
     */
    public function execute(string $operation, array $input): array
    {
        WorkspacePermissions::authorize($this->context, 'workspace.read');
        if ($operation === 'getProfile') {
            $user = $this->repository->user($this->context['user']['id']);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'revision' => (int) $user->profile_revision,
            ];
        }
        if ($operation === 'getWorkspaceSettings') {
            return $this->listing();
        }
        if ($operation === 'updateWorkspace') {
            WorkspacePermissions::authorize($this->context, 'workspace.update');
        }
        if ($operation === 'setWorkspaceMemberRole') {
            // 每次重試亦重查目前資格及可處理的目標，不從快取放行。
            $this->authorizeRoleChange($input);
        }
        $store = (new RecordStore)->forWorkspace($this->context['workspace']['id']);

        return (new RequestReplay)->execute($store, 'requests', $this->context['user']['id'], $operation, $input, fn () => $this->write($operation, $input));
    }

    /** 列出固定角色；角色ID只查映射，不作放行捷徑。
     * @return array
     */
    private function roles(): array
    {
        return array_map(fn ($id, $label) => [
            'id' => $id,
            'label' => $label,
            'permissions' => WorkspacePermissions::resolve($id),
        ], ['owner', 'manager', 'user'], ['擁有者', '管理者', '一般成員']);
    }

    /** 組合目前空間設定；普通成員不取得全員目錄。
     * @return array
     */
    private function listing(): array
    {
        $workspace = $this->repository->workspace($this->context['workspace']['id']);
        $members = [];
        if (in_array('workspace.members.read', $this->context['permissions'], true)) {
            foreach ($this->repository->members($workspace) as $member) {
                $members[] = [
                    'id' => $member->user_id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'roleId' => $member->role,
                    'revision' => (int) $member->role_revision,
                    'canChange' => $this->canChange($member),
                ];
            }
        }

        return [
            'workspace' => [
                'id' => $workspace->public_id,
                'name' => $workspace->name,
                'revision' => (int) $workspace->settings_revision,
            ],
            'members' => $members,
            'roles' => $this->roles(),
            'roleId' => $this->context['role'],
            'permissions' => $this->context['permissions'],
        ];
    }

    /** 判斷目標是否在可管理授權範圍內，保留ownership資格。
     * @param  WorkspaceMember  $member  同空間成員
     * @return bool
     */
    private function canChange(WorkspaceMember $member): bool
    {
        if (! in_array('workspace.members.manage', $this->context['permissions'], true)) {
            return false;
        }
        $current = WorkspacePermissions::resolve($member->role);

        return ! in_array('workspace.ownership', $current, true) && ! array_diff($current, $this->context['permissions']);
    }

    /** 驗證角色指派的目標、可授權限及ownership邊界。
     * @param  array  $input  原始輸入
     * @return WorkspaceMember
     */
    private function authorizeRoleChange(array $input): WorkspaceMember
    {
        WorkspacePermissions::authorize($this->context, 'workspace.members.manage');
        $workspace = $this->repository->workspace($this->context['workspace']['id']);
        $member = $this->repository->member($workspace, Fields::text($input['userId'] ?? null, '成員', 100, true));
        if (! $member) {
            DomainError::fail('NOT_FOUND', '找不到有效的工作空間成員');
        }
        if (! $this->canChange($member)) {
            DomainError::fail('FORBIDDEN', '無法調整此成員的授權；擁有者資格需由獨立轉移流程處理');
        }
        $roleId = Fields::text($input['roleId'] ?? null, '工作空間角色', 100, true);
        $roles = array_column($this->roles(), null, 'id');
        $grant = $roles[$roleId]['permissions'] ?? [];
        if (! $grant || in_array('workspace.ownership', $grant, true)) {
            DomainError::fail('INVALID_INPUT', '請選擇可指派的工作空間角色');
        }
        if (array_diff($grant, $this->context['permissions'])) {
            DomainError::fail('FORBIDDEN', '不能授予超出自己有效權限的角色');
        }

        return $member;
    }

    /** 保存設定欄位，衝突時不改任何資料或重試紀錄。
     * @param  string  $operation  已授權的寫入名稱
     * @param  array  $input  原始輸入
     * @return array
     */
    private function write(string $operation, array $input): array
    {
        if ($operation === 'updateProfile') {
            $user = $this->repository->user($this->context['user']['id']);
            $this->checkRevision($input, (int) $user->profile_revision);
            $name = Fields::text($input['name'] ?? null, '顯示名稱', 60, true);
            $saved = $this->repository->renameUser($user, $name);
            $result = [
                'id' => $user->id,
                'revision' => $user->profile_revision + 1,
            ];
        } elseif ($operation === 'updateWorkspace') {
            $workspace = $this->repository->workspace($this->context['workspace']['id']);
            $this->checkRevision($input, (int) $workspace->settings_revision);
            $name = Fields::text($input['name'] ?? null, '工作空間名稱', 60, true);
            $saved = $this->repository->renameWorkspace($workspace, $name);
            $result = [
                'id' => $workspace->public_id,
                'revision' => $workspace->settings_revision + 1,
            ];
        } else {
            $member = $this->authorizeRoleChange($input);
            $this->checkRevision($input, (int) $member->role_revision);
            $roleId = Fields::text($input['roleId'], '工作空間角色', 100, true);
            $saved = $this->repository->changeRole($member, $roleId);
            $result = [
                'id' => $member->user_id,
                'revision' => $member->role_revision + 1,
            ];
        }
        if (! $saved) {
            DomainError::fail('REVISION_CONFLICT', '設定已被更新，請重新載入後再修改');
        }
        (new ProjectSignals($this->context))->workspaceChanged();

        return $result;
    }

    /** 驗證前端保留的基準版本，不能使用背景更新偷換版本。
     * @param  array  $input  原始輸入
     * @param  int  $revision  目前SQL版本
     * @return void
     */
    private function checkRevision(array $input, int $revision): void
    {
        if (($input['expectedRevision'] ?? null) !== $revision) {
            DomainError::fail('REVISION_CONFLICT', '設定已被更新，你的輸入已保留；請重新載入後再修改');
        }
    }
}
