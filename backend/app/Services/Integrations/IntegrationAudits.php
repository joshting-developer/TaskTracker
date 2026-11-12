<?php

namespace App\Services\Integrations;

use App\Data\TokenContext;
use App\Data\WorkspaceContext;
use App\Repositories\IntegrationAuditRepository;

/** 同業務交易追加最小來源，唯讀查詢不新增稽核寫量。 */
class IntegrationAudits
{
    /**
     * 注入限定資料來源，不在服務組合SQL。
     *
     * @param  IntegrationAuditRepository  $records  稽核保存與本人分頁
     */
    public function __construct(private IntegrationAuditRepository $records) {}

    /**
     * 記錄已成功的受信任用例識別，失敗時隨外層交易回滾。
     *
     * @param  WorkspaceContext  $workspace  當次已重驗的工作區資格
     * @param  string  $operation  伺服器固定operationId，不能用HTTP path或payload代替
     * @param  string|null  $resourceId  已授權資源識別，不含業務內容
     * @param  TokenContext|null  $token  Bearer來源；null代表Session管理操作
     * @param  int|null  $revision  寫入後的資源revision，供回音辨識
     * @param  string|null  $clientReference  外部系統提供的run等參照，已驗格式
     * @return void
     */
    public function append(WorkspaceContext $workspace, string $operation, ?string $resourceId = null, ?TokenContext $token = null, ?int $revision = null, ?string $clientReference = null): void
    {
        $this->records->append($workspace, [
            'actor_user_id' => $workspace->userId, 'source' => $token ? 'token' : 'session',
            'token_id' => $token?->tokenId, 'token_name' => $token?->tokenName,
            'abilities' => $token?->abilities ?? [], 'operation' => $operation,
            'resource_id' => $resourceId, 'resource_revision' => $revision, 'client_reference' => $clientReference, 'occurred_at' => now(),
        ]);
    }

    /**
     * 本人目前工作區的來源紀錄，每頁20筆且id倒序，不列其他使用者。
     *
     * @param  WorkspaceContext  $context  Session重驗後的可信資格
     * @param  int  $page  已驗證正整數頁碼
     * @return array 有界安全metadata分頁
     */
    public function listing(WorkspaceContext $context, int $page): array
    {
        $result = $this->records->page($context, $page);
        $items = $result['items'];
        $total = $result['total'];

        return ['items' => $items->map(fn ($row) => ['id' => (string) $row->id, 'userId' => $row->actor_user_id,
            'source' => $row->source, 'tokenId' => $row->token_id, 'tokenName' => $row->token_name,
            'abilities' => $row->abilities, 'operation' => $row->operation, 'resourceId' => $row->resource_id,
            'resourceRevision' => $row->resource_revision, 'clientReference' => $row->client_reference,
            'occurredAt' => $row->occurred_at->utc()->toISOString()])->all(),
            'page' => $page, 'perPage' => 20, 'total' => $total, 'lastPage' => max(1, (int) ceil($total / 20))];
    }
}
