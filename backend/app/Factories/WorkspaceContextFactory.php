<?php

namespace App\Factories;

use App\Data\WorkspaceContext;
use App\Models\User;
use App\Services\AccountService;

/** 每次用例重驗伺服器身分後建立Context；不快取Actor、Workspace或有效權限。 */
class WorkspaceContextFactory
{
    /** 注入既有資格驗證協作者，不複製另一套角色或授權判斷。 */
    public function __construct(private AccountService $accounts) {}

    /**
     * 對缺失／失效資格維持既有UNAUTHENTICATED，不用輸入中的角色放行。
     *
     * @param  User|null  $user  HTTP Session已取得的新鮮帳號，非payload帳號ID
     * @param  mixed  $workspaceId  Session的目前工作空間，交AccountService驗型別及資格
     * @return WorkspaceContext 本次資格快照；下次交易必須重新建立
     */
    public function verify(?User $user, mixed $workspaceId): WorkspaceContext
    {
        return new WorkspaceContext($this->accounts->context($user, $workspaceId));
    }
}
