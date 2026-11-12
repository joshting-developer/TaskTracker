<?php

namespace App\Services\Integrations;

use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Models\PersonalAccessToken;
use App\Repositories\PersonalTokenRepository;
use Carbon\CarbonImmutable;

/** 本人Token資料操作；交易、User鎖與Session資格由共用WorkspaceTransaction擁有。 */
class PersonalTokens
{
    /**
     * 注入同交易的來源紀錄，不在Service取得HTTP或Session。
     *
     * @param  IntegrationAudits  $audits  同交易的整合來源紀錄
     * @param  PersonalTokenRepository  $tokens  本人限定PAT資料入口
     */
    public function __construct(private IntegrationAudits $audits, private PersonalTokenRepository $tokens) {}

    /**
     * 以擁有者與固定工作區在SQL限定範圍，穩定倒序分頁metadata。
     *
     * @param  WorkspaceContext  $context  當次可信資格
     * @param  int  $page  頁碼
     * @return array 每頁20筆，不含原文或雜湊
     */
    public function listing(WorkspaceContext $context, int $page): array
    {
        $result = $this->tokens->page($context, $page);

        return [...$result, 'items' => $result['items']->map(fn ($token) => $this->metadata($token))->all(), 'perPage' => 20];
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
        if ($this->tokens->issued($context->userId, $input['requestId'])) {
            DomainError::fail('REQUEST_CONFLICT', '此建立請求已處理；原文不會再次顯示，請從清單核對後撤銷或另建');
        }
        $today = CarbonImmutable::now('Asia/Taipei')->startOfDay();
        $expires = isset($input['expiresAt']) ? CarbonImmutable::parse($input['expiresAt'], 'Asia/Taipei')->startOfDay() : $today->addMonthNoOverflow();
        if ($expires <= CarbonImmutable::now('Asia/Taipei') || $expires > $today->addYearsNoOverflow(2)) {
            DomainError::fail('INVALID_INPUT', '到期日須在未來且最多兩年');
        }
        // Workspace必填欄位需與Sanctum雜湊同次INSERT；沿用官方generateTokenString／findToken格式。
        $name = trim($input['name']);
        if ($name === '') {
            DomainError::fail('INVALID_INPUT', '請填寫Token名稱');
        }
        $plain = $this->tokens->generatePlain($context->userId);
        $token = $this->tokens->create($context, ['name' => $name, 'abilities' => array_values(array_unique($input['abilities'])), 'token' => hash('sha256', $plain), 'expires_at' => $expires, 'issuance_request_id' => $input['requestId']]);
        $this->audits->append($context, 'personalTokens.create', (string) $token->id);

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
        $token = $this->tokens->findOwned($context, $id);
        if (! $token) {
            DomainError::fail('NOT_FOUND', '找不到Token');
        }
        if ($token->revoked_at === null) {
            $token->revoked_at = now();
            $this->tokens->save($token);
            $this->audits->append($context, 'personalTokens.revoke', (string) $token->id);
        }

        return $this->metadata($token);
    }

    /**
     * 轉成可公開的Token資訊，不含原文或雜湊。
     *
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
