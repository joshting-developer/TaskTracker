<?php

namespace App\Repositories;

use App\Models\DashboardPreference;
use App\Models\WorkspaceMember;

/** TT-186：首頁偏好SQL, 一律以有效工作區成員為範圍 */
class DashboardPreferenceRepository
{
    /** 從可信公開ID重驗有效成員
     * @param  string  $workspaceId  工作區公開ID
     * @param  string  $userId  可信帳號
     * @return WorkspaceMember|null 有效成員
     */
    public function member(string $workspaceId, string $userId): ?WorkspaceMember
    {
        return WorkspaceMember::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))->where('user_id', $userId)->whereNull('disabled_at')->first(['id', 'workspace_id', 'user_id']);
    }

    /** 讀取成員偏好
     * @param  WorkspaceMember  $member  有效成員
     * @param  bool  $lock  是否鎖定供寫入
     * @return DashboardPreference|null 偏好
     */
    public function find(WorkspaceMember $member, bool $lock = false): ?DashboardPreference
    {
        return DashboardPreference::where('workspace_id', $member->workspace_id)->where('member_id', $member->id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
    }

    /** 建立或更新偏好並遞增版本
     * @param  WorkspaceMember  $member  有效成員
     * @param  DashboardPreference|null  $current  已鎖定的既有偏好
     * @param  array<int,string|array>  $widgets  已驗證的小工具順序
     * @return DashboardPreference 保存後偏好
     */
    public function save(WorkspaceMember $member, ?DashboardPreference $current, array $widgets): DashboardPreference
    {
        if (! $current) {
            return DashboardPreference::create(['workspace_id' => $member->workspace_id, 'member_id' => $member->id, 'widgets' => $widgets, 'revision' => 1]);
        }
        $current->update(['widgets' => $widgets, 'revision' => $current->revision + 1]);

        return $current;
    }
}
