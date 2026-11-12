<?php

namespace App\Data\Writes;

/** Settings領域已驗證傳輸資料；明確型別與原始簽章分開保存。 */
final readonly class SettingsWriteData extends RpcWriteData
{
    /**
     * 由專用Request建立，不接收HTTP物件或取得可信身分。
     *
     * @param  string  $operation  固定路由操作
     * @param  string  $requestId  操作識別
     * @param  ?string  $id  目標資源
     * @param  ?int  $expectedRevision  版本視圖
     * @param  ?string  $name  傳輸的name欄位，未提供時為null
     * @param  ?string  $userId  傳輸的userId欄位，未提供時為null
     * @param  ?string  $roleId  傳輸的roleId欄位，未提供時為null
     * @param  array<string,mixed>  $original  原始輸入及未知欄位，禁止日誌輸出
     */
    public function __construct(string $operation, string $requestId, ?string $id, ?int $expectedRevision, public ?string $name, public ?string $userId, public ?string $roleId, array $original)
    {
        parent::__construct($operation, $requestId, $id, $expectedRevision, $original);
    }
}
