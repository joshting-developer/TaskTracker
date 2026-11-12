<?php

namespace App\Data\Writes;

/** Account領域已驗證傳輸資料；明確型別與原始簽章分開保存。 */
final readonly class AccountWriteData extends RpcWriteData
{
    /**
     * 由專用Request建立，不接收HTTP物件或取得可信身分。
     *
     * @param  string  $operation  固定路由操作
     * @param  string  $requestId  操作識別
     * @param  ?string  $id  目標資源
     * @param  ?int  $expectedRevision  版本視圖
     * @param  ?string  $name  傳輸的name欄位，未提供時為null
     * @param  ?string  $email  傳輸的email欄位，未提供時為null
     * @param  ?string  $password  傳輸的password欄位，未提供時為null
     * @param  ?string  $workspaceName  傳輸的workspaceName欄位，未提供時為null
     * @param  ?string  $workspaceId  傳輸的workspaceId欄位，未提供時為null
     * @param  ?string  $inviteToken  傳輸的inviteToken欄位，未提供時為null
     * @param  array<string,mixed>  $original  原始輸入及未知欄位，禁止日誌輸出
     */
    public function __construct(string $operation, string $requestId, ?string $id, ?int $expectedRevision, public ?string $name, public ?string $email, public ?string $password, public ?string $workspaceName, public ?string $workspaceId, public ?string $inviteToken, array $original)
    {
        parent::__construct($operation, $requestId, $id, $expectedRevision, $original);
    }
}
