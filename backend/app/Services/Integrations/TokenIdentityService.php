<?php

namespace App\Services\Integrations;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\PersonalTokenRepository;

/** Token HTTP邊界的即時資料用例；每次Repository重讀，不保存先前資格。 */
class TokenIdentityService
{
    /**
     * 注入無租戶狀態的Token與帳號資料入口。
     *
     * @param  PersonalTokenRepository  $tokens  PAT雜湊查詢
     * @param  AccountRepository  $accounts  帳號資料入口
     */
    public function __construct(private PersonalTokenRepository $tokens, private AccountRepository $accounts) {}

    /**
     * 取得Bearer對應的當次模型，資格與Scope由交易邊界重驗。
     *
     * @param  string  $plain  Bearer原文，禁止紀錄
     * @return ?PersonalAccessToken 當次模型
     */
    public function token(string $plain): ?PersonalAccessToken
    {
        return $this->tokens->findByPlain($plain);
    }

    /**
     * 解析Token所綁定的Workspace公開ID。
     *
     * @param  int  $id  內部Workspace鍵
     * @return ?string 公開Workspace ID
     */
    public function workspaceId(int $id): ?string
    {
        return $this->tokens->workspaceId($id);
    }

    /**
     * 查當次Token所有者，不復用其他請求的Auth guard。
     *
     * @param  string  $id  Token內的帳號ID
     * @return ?User 當次帳號
     */
    public function user(string $id): ?User
    {
        return $this->accounts->find($id);
    }
}
