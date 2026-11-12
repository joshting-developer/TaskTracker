<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

/** 帳號全域識別及明確Workspace資格資料入口；不判斷登入或業務權限。 */
class AccountRepository
{
    /**
     * 依登入Email查單一帳號，保留舊密碼雜湊升級所需欄位。
     *
     * @param  string  $email  已正規化Email
     * @return ?User 存在時的帳號
     */
    public function byEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * 依可信帳號ID重新取得當次模型，不保存Auth快取。
     *
     * @param  ?string  $id  伺服器取得的User ID
     * @return ?User 新鮮模型或null
     */
    public function find(?string $id): ?User
    {
        return $id ? User::find($id) : null;
    }

    /**
     * 註冊前檢查Email唯一性，最後仍由DB唯一鍵保護。
     *
     * @param  string  $email  已正規化Email
     * @return bool 是否存在
     */
    public function emailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    /**
     * 以Service已驗證欄位建立帳號，與外層交易一起提交。
     *
     * @param  array  $attributes  已驗證帳號欄位
     * @return User 保存的帳號
     */
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    /**
     * 保存驗證成功後的密碼雜湊升級，不自行驗密碼。
     *
     * @param  User  $user  已由Service驗證的帳號
     * @param  array  $attributes  明確要更新的欄位
     * @return void
     */
    public function update(User $user, array $attributes): void
    {
        $user->forceFill($attributes)->save();
    }

    /**
     * 最小投影解析Workspace名稱與內部鍵。
     *
     * @param  string  $publicId  Workspace公開ID
     * @return ?Workspace 存在時的Workspace
     */
    public function workspace(string $publicId): ?Workspace
    {
        return Workspace::where('public_id', $publicId)->first(['id', 'name']);
    }

    /**
     * 只讀指定帳號在指定Workspace仍有效的角色映射，不載入成員全集。
     *
     * @param  int  $workspaceId  Workspace內部鍵
     * @param  string  $userId  操作者ID
     * @return ?WorkspaceMember 成員映射或null
     */
    public function membership(int $workspaceId, string $userId): ?WorkspaceMember
    {
        return WorkspaceMember::where('workspace_id', $workspaceId)->where('user_id', $userId)->whereNull('disabled_at')->first(['role']);
    }
}
