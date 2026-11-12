<?php

namespace App\Services\Integrations;

use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** 本人Token資料操作；交易、User鎖與Session資格由共用WorkspaceTransaction擁有。 */
class PersonalTokens
{
    /**
     * 以擁有者與固定工作區在SQL限定範圍，穩定倒序分頁metadata。
     *
     * @param  WorkspaceContext  $context  當次可信資格
     * @param  int  $page  頁碼
     * @return array 每頁20筆，不含原文或雜湊
     */
    public function listing(WorkspaceContext $context, int $page): array
    {
        $query = $this->owned($context);
        $total = $query->count();
        $last = max(1, (int) ceil($total / 20));
        $page = min($page, $last);
        $items = $query->orderByDesc('id')->forPage($page, 20)->get(['id', 'name', 'abilities', 'created_at', 'expires_at', 'revoked_at']);

        return ['items' => $items->map(fn ($token) => $this->metadata($token))->all(), 'page' => $page, 'perPage' => 20, 'total' => $total, 'lastPage' => $last];
    }

    /**
     * 保存Sanctum相容雜湊與必填工作區，同requestId不再次揭露原文。
     *
     * @param  WorkspaceContext  $context  當次資格
     * @param  array  $input  已驗輸入
     * @return array 一次性的原文與安全metadata
     */
    public function create(WorkspaceContext $context, array $input): array
    {
        if (PersonalAccessToken::where('tokenable_id', $context->userId)->where('issuance_request_id', $input['requestId'])->exists()) {
            DomainError::fail('REQUEST_CONFLICT', '此建立請求已處理；原文不會再次顯示，請從清單核對後撤銷或另建');
        }
        $today = CarbonImmutable::now('Asia/Taipei')->startOfDay();
        $expires = isset($input['expiresAt']) ? CarbonImmutable::parse($input['expiresAt'], 'Asia/Taipei')->startOfDay() : $today->addMonthNoOverflow();
        if ($expires <= CarbonImmutable::now('Asia/Taipei') || $expires > $today->addYearsNoOverflow(2)) {
            DomainError::fail('INVALID_INPUT', '到期日須在未來且最多兩年');
        }
        // Workspace必填欄位需與Sanctum雜湊同次INSERT；沿用官方generateTokenString／findToken格式。
        $plain = User::findOrFail($context->userId)->generateTokenString();
        $token = new PersonalAccessToken;
        $token->forceFill(['tokenable_type' => User::class, 'tokenable_id' => $context->userId,
            'workspace_id' => Workspace::where('public_id', $context->workspaceId)->sole(['id'])->id,
            'name' => trim($input['name']), 'abilities' => array_values(array_unique($input['abilities'])),
            'token' => hash('sha256', $plain), 'expires_at' => $expires, 'issuance_request_id' => $input['requestId']]);
        if ($token->name === '') {
            DomainError::fail('INVALID_INPUT', '請填寫Token名稱');
        }
        $token->save();

        return ['token' => $this->metadata($token), 'plainTextToken' => $token->getKey().'|'.$plain];
    }

    /**
     * 撤銷本人Token並保留metadata，重試不重寫已撤銷時間。
     *
     * @param  WorkspaceContext  $context  當次資格
     * @param  string  $id  本人Token ID
     * @return array 撤銷後metadata
     */
    public function revoke(WorkspaceContext $context, string $id): array
    {
        $token = $this->owned($context)->whereKey($id)->first();
        if (! $token) {
            DomainError::fail('NOT_FOUND', '找不到Token');
        }
        if ($token->revoked_at === null) {
            $token->revoked_at = now();
            $token->save();
        }

        return $this->metadata($token);
    }

    /**
     * @param  WorkspaceContext  $context  可信資格
     * @return Builder 本人及工作區限定查詢
     */
    private function owned(WorkspaceContext $context): Builder
    {
        return PersonalAccessToken::query()->where('tokenable_type', User::class)->where('tokenable_id', $context->userId)
            ->whereIn('workspace_id', Workspace::where('public_id', $context->workspaceId)->select('id'));
    }

    /**
     * @param  PersonalAccessToken  $token  已限定擁有者的Model
     * @return array 對外安全投影，不自動serializeModel
     */
    private function metadata(PersonalAccessToken $token): array
    {
        return ['id' => (string) $token->id, 'name' => $token->name, 'abilities' => $token->abilities,
            'createdAt' => $token->created_at->toISOString(), 'expiresAt' => $token->expires_at->toISOString(),
            'status' => $token->revoked_at ? 'revoked' : ($token->expires_at->isPast() ? 'expired' : 'active')];
    }
}
