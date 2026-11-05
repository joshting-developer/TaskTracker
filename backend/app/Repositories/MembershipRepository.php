<?php

namespace App\Repositories;

use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;

/** 查詢本人的工作空間資格，預載工作區避免逐筆讀取。 */
class MembershipRepository
{
    /**
     * 取得指定帳號仍有效的工作空間資格。
     *
     * @param  string  $userId  已驗證的本人帳號
     * @return Collection 有工作區關聯的成員資格
     */
    public function activeForUser(string $userId): Collection
    {
        return WorkspaceMember::query()->where('user_id', $userId)->whereNull('disabled_at')->with('workspace')->orderBy('id')->get();
    }
}
