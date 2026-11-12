<?php

namespace App\Data;

use App\Models\User;

/** 帳號服務的內部結果，HTTP層負責Session，不直接序列化Model。 */
final readonly class AuthenticatedAccountData
{
    /**
     * 保存登入／註冊驗證後的帳號與要寫入Session的工作區。
     *
     * @param  User  $user  已通過憑證或註冊驗證的帳號
     * @param  string  $workspaceId  已驗證的工作空間資格
     */
    public function __construct(public User $user, public string $workspaceId) {}
}
