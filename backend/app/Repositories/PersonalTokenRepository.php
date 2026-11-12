<?php

namespace App\Repositories;

use App\Data\WorkspaceContext;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** 個人PAT限定本人／Workspace的保存與查詢；Scope／期限決策由Service負責。 */
class PersonalTokenRepository
{
    /**
     * 限定本人及目前Workspace，不將Builder傳出Repository。
     *
     * @param  WorkspaceContext  $context  已驗證資格
     * @return Builder 限定查詢
     */
    private function owned(WorkspaceContext $context): Builder
    {
        return PersonalAccessToken::query()->where('tokenable_type', User::class)->where('tokenable_id', $context->userId)->whereIn('workspace_id', Workspace::where('public_id', $context->workspaceId)->select('id'));
    }

    /**
     * 只讀安全metadata且每頁20筆，不下載Token原文或雜湊。
     *
     * @param  WorkspaceContext  $context  當次資格
     * @param  int  $page  正整數頁碼
     * @return array{items:Collection,total:int,page:int,lastPage:int} 有界metadata
     */
    public function page(WorkspaceContext $context, int $page): array
    {
        $query = $this->owned($context);
        $total = $query->count();
        $last = max(1, (int) ceil($total / 20));
        $page = min($page, $last);

        return ['items' => $query->orderByDesc('id')->forPage($page, 20)->get(['id', 'name', 'abilities', 'created_at', 'expires_at', 'revoked_at']), 'total' => $total, 'page' => $page, 'lastPage' => $last];
    }

    /**
     * 檢查同帳號的Token發行識別，避免重播一次性原文。
     *
     * @param  string  $userId  本人ID
     * @param  string  $requestId  UUID操作識別
     * @return bool 是否已發行
     */
    public function issued(string $userId, string $requestId): bool
    {
        return PersonalAccessToken::where('tokenable_id', $userId)->where('issuance_request_id', $requestId)->exists();
    }

    /**
     * 使用Sanctum格式產生Token原文；只回給Service一次性輸出。
     *
     * @param  string  $userId  本人ID
     * @return string 原文，禁止寫入日誌
     */
    public function generatePlain(string $userId): string
    {
        return User::findOrFail($userId)->generateTokenString();
    }

    /**
     * 建立帶Workspace及雜湊的Token，未保存原文。
     *
     * @param  WorkspaceContext  $context  已驗證本人／Workspace
     * @param  array  $attributes  Service驗證的metadata與雜湊
     * @return PersonalAccessToken 保存的Token
     */
    public function create(WorkspaceContext $context, array $attributes): PersonalAccessToken
    {
        $token = new PersonalAccessToken;
        $token->forceFill([...$attributes, 'tokenable_type' => User::class, 'tokenable_id' => $context->userId, 'workspace_id' => Workspace::where('public_id', $context->workspaceId)->sole(['id'])->id]);
        $token->save();

        return $token;
    }

    /**
     * 只取得本人目前Workspace指定Token。
     *
     * @param  WorkspaceContext  $context  當次資格
     * @param  string  $id  Token ID
     * @return ?PersonalAccessToken 存在時的Token
     */
    public function findOwned(WorkspaceContext $context, string $id): ?PersonalAccessToken
    {
        return $this->owned($context)->whereKey($id)->first();
    }

    /**
     * 保存由Service決定的撤銷時間。
     *
     * @param  PersonalAccessToken  $token  已限定本人之模型
     * @return void
     */
    public function save(PersonalAccessToken $token): void
    {
        $token->save();
    }

    /**
     * 每次依Sanctum雜湊取得Token，不使用上次Auth guard快取。
     *
     * @param  string  $plain  Bearer原文
     * @return ?PersonalAccessToken 對應模型
     */
    public function findByPlain(string $plain): ?PersonalAccessToken
    {
        return PersonalAccessToken::findToken($plain);
    }

    /**
     * 將Token的內部Workspace鍵解析為公開scope。
     *
     * @param  int  $id  Workspace內部ID
     * @return ?string Workspace公開ID
     */
    public function workspaceId(int $id): ?string
    {
        return Workspace::whereKey($id)->value('public_id');
    }
}
