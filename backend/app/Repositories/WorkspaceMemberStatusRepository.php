<?php

namespace App\Repositories;

use App\Database\DatabaseTimes;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceMemberStatusEvent;

/** 只保存同工作區的狀態與事件, 不決定誰能管理 */
class WorkspaceMemberStatusRepository
{
    /** 取得指定空間的有效或停用成員, 不讀其他人
     * @param  string  $workspace  公開工作區ID
     * @param  string  $user  使用者ID
     * @return ?WorkspaceMember 同空間資格
     */
    public function member(string $workspace, string $user): ?WorkspaceMember
    {
        return WorkspaceMember::query()->join('workspaces as w', 'w.id', '=', 'workspace_members.workspace_id')->where('w.public_id', $workspace)->where('workspace_members.user_id', $user)->first(['workspace_members.id', 'workspace_members.workspace_id', 'workspace_members.user_id', 'workspace_members.role', 'workspace_members.role_revision', 'workspace_members.disabled_at']);
    }

    /** 以角色共用版本限制狀態變更
     * @param  WorkspaceMember  $member  已授權同空間資格
     * @param  ?string  $disabledAt  停用時間, null為恢復
     * @return bool 是否成功更新
     */
    public function update(WorkspaceMember $member, ?string $disabledAt): bool
    {
        return WorkspaceMember::whereKey($member->id)->where('workspace_id', $member->workspace_id)->where('role_revision', $member->role_revision)->update(['disabled_at' => $disabledAt === null ? null : DatabaseTimes::store($disabledAt), 'role_revision' => $member->role_revision + 1]) === 1;
    }

    /** 保存與變更同交易的操作者及版本
     * @param  WorkspaceMember  $member  目標資格
     * @param  string  $actor  可信操作者ID
     * @param  string  $status  啟用或停用
     * @param  string  $time  變更時間
     * @return void
     */
    public function record(WorkspaceMember $member, string $actor, string $status, string $time): void
    {
        WorkspaceMemberStatusEvent::create(['workspace_id' => $member->workspace_id, 'member_id' => $member->id, 'actor_id' => $actor, 'status' => $status, 'revision' => $member->role_revision + 1, 'occurred_at' => DatabaseTimes::store($time)]);
    }
}
