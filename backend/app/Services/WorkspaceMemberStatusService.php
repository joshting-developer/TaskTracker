<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\WorkspaceContext;
use App\Database\DatabaseTimes;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Factories\RecordStoreFactory;
use App\Repositories\WorkspaceMemberStatusRepository;

/** 同空間資格停用及恢復, 不修改帳號或原有專案ACL */
class WorkspaceMemberStatusService
{
    /** 注入資料及共用receipt協作者, 不在建構時查詢
     * @param  WorkspaceMemberStatusRepository  $members  成員狀態與事件資料
     * @param  RecordStoreFactory  $records  共用Workspace receipt儲存入口
     * @param  WorkspaceMemberCapacity  $capacity  恢復前的人數規則
     * @param  RequestReplay  $replay  授權後重試
     */
    public function __construct(private WorkspaceMemberStatusRepository $members, private RecordStoreFactory $records, private RequestReplay $replay, private WorkspaceMemberCapacity $capacity) {}

    /** 在HTTP已取鎖交易中先驗目前資格, 再執行或重播
     * @param  WorkspaceContext  $context  當次可信操作者與空間
     * @param  array<string,mixed>  $input  目標、狀態、版本及requestId
     * @return array<string,mixed> 最小狀態與版本
     */
    public function change(WorkspaceContext $context, array $input): array
    {
        WorkspacePermissions::authorize($context->legacyIdentity(), 'workspace.members.manage');
        $target = Fields::text($input['userId'] ?? null, '成員', 100, true);
        $member = $this->members->member($context->workspaceId, $target) ?? DomainError::fail('NOT_FOUND', '找不到工作空間成員');
        if ($target === $context->userId || ! WorkspacePermissions::canManageRole($context->permissions, $member->role)) {
            DomainError::fail('FORBIDDEN', '不能透過此操作調整本人或擁有者資格');
        }
        $status = Fields::choice($input['status'] ?? null, ['active', 'disabled'], '成員狀態');

        return $this->replay->execute($this->records->forWorkspace($context->workspaceId), 'requests', $context->userId, 'setWorkspaceMemberStatus', $input, function () use ($member, $context, $input, $status) {
            if (($input['expectedRevision'] ?? null) !== (int) $member->role_revision) {
                DomainError::fail('REVISION_CONFLICT', '成員已被更新, 請重新載入');
            }
            if (($member->disabled_at !== null) === ($status === 'disabled')) {
                return ['id' => $member->user_id, 'revision' => (int) $member->role_revision, 'status' => $status, 'disabledAt' => DatabaseTimes::public($member->disabled_at)];
            }
            if ($status === 'active') {
                $this->capacity->assertAvailable($context->workspaceId);
            }
            $time = now()->utc()->toISOString();
            $disabledAt = $status === 'disabled' ? $time : null;
            if (! $this->members->update($member, $disabledAt)) {
                DomainError::fail('REVISION_CONFLICT', '成員已被更新, 請重新載入');
            }
            $this->members->record($member, $context->userId, $status, $time);

            return ['id' => $member->user_id, 'revision' => (int) $member->role_revision + 1, 'status' => $status, 'disabledAt' => $disabledAt];
        });
    }
}
