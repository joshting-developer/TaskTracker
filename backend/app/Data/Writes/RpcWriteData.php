<?php

namespace App\Data\Writes;

/** 已驗證的單一領域RPC輸入，保留原始payload供既有領域驗證與receipt簽章。 */
abstract readonly class RpcWriteData
{
    /**
     * 保存已驗證的傳輸值；缺漏與null不得互相代換。
     *
     * @param  string  $operation  路由選定的操作
     * @param  string  $requestId  原始操作識別
     * @param  ?string  $id  目標資源
     * @param  ?int  $expectedRevision  輸入版本的型別化視圖，原始值仍供領域嚴格判斷
     * @param  array<string,mixed>  $original  原始輸入，不序列化至log
     */
    public function __construct(public string $operation, public string $requestId, public ?string $id, public ?int $expectedRevision, private array $original) {}

    /**
     * 只在既有Application Service相容邊界取原始輸入，不以validated()改寫舊receipt簽章。
     *
     * @return array<string,mixed> 完整原始輸入
     */
    public function input(): array
    {
        return $this->original;
    }
}
