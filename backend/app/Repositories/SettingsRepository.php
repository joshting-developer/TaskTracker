<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;

/** 以明確SQL欄位讀寫個人及空間設定；不變更一般業務DTO。 */
class SettingsRepository
{
    /** 取得可信身分的帳號。
     * @param  string  $userId  Session帳號ID
     * @return User
     */
    public function user(string $userId): User
    {
        return User::findOrFail($userId);
    }

    /** 以公開空間ID取得內部SQL模型。
     * @param  string  $workspaceId  Session空間ID
     * @return Workspace
     */
    public function workspace(string $workspaceId): Workspace
    {
        return Workspace::where('public_id', $workspaceId)->firstOrFail();
    }

    /** 取得指定空間有效成員並預載帳號。
     * @param  Workspace  $workspace  已驗證空間
     * @return Collection
     */
    public function members(Workspace $workspace): Collection
    {
        return $workspace->members()->whereNull('disabled_at')->with('user')->orderBy('id')->get();
    }

    /** 取得同空間的指定成員，不以全域帳號查詢取代scope。
     * @param  Workspace  $workspace  已驗證空間
     * @param  string  $userId  目標帳號ID
     * @return ?WorkspaceMember
     */
    public function member(Workspace $workspace, string $userId): ?WorkspaceMember
    {
        return $workspace->members()->where('user_id', $userId)->whereNull('disabled_at')->first();
    }

    /** 用條件版本更新姓名。
     * @param  User  $user  本人帳號
     * @param  string  $name  新顯示名稱
     * @return bool
     */
    public function renameUser(User $user, string $name): bool
    {
        return User::whereKey($user->id)->where('profile_revision', $user->profile_revision)->update([
            'name' => $name,
            'profile_revision' => $user->profile_revision + 1,
        ]) === 1;
    }

    /** 用條件版本更新空間名稱。
     * @param  Workspace  $workspace  已授權空間
     * @param  string  $name  新名稱
     * @return bool
     */
    public function renameWorkspace(Workspace $workspace, string $name): bool
    {
        return Workspace::whereKey($workspace->id)->where('settings_revision', $workspace->settings_revision)->update([
            'name' => $name,
            'settings_revision' => $workspace->settings_revision + 1,
        ]) === 1;
    }

    /** 用同空間模型及條件版本更新角色。
     * @param  WorkspaceMember  $member  已授權同空間成員
     * @param  string  $roleId  固定角色映射ID
     * @return bool
     */
    public function changeRole(WorkspaceMember $member, string $roleId): bool
    {
        return WorkspaceMember::whereKey($member->id)->where('workspace_id', $member->workspace_id)->where('role_revision', $member->role_revision)->update([
            'role' => $roleId,
            'role_revision' => $member->role_revision + 1,
        ]) === 1;
    }
}
