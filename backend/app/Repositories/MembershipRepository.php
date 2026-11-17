<?php

namespace App\Repositories;

use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Collection;

/** 查詢本人的工作空間資格，預載工作區避免逐筆讀取。 */
class MembershipRepository
{
    /** 在工作空間鎖內計算有效資格, 包含Owner而不計待接受邀請
     * @param  string  $workspaceId  工作空間公開ID
     * @return int 有效成員數
     */
    public function activeCount(string $workspaceId): int
    {
        return WorkspaceMember::query()->whereHas('workspace', fn ($query) => $query->where('public_id', $workspaceId))->whereNull('disabled_at')->count();
    }

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
