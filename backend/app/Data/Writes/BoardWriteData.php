<?php

namespace App\Data\Writes;

use App\Data\BoardRuleData;

/** Board領域已驗證傳輸資料；明確型別與原始簽章分開保存。 */
final readonly class BoardWriteData extends RpcWriteData
{
    /**
     * 由專用Request建立，不接收HTTP物件或取得可信身分。
     *
     * @param  string  $operation  固定路由操作
     * @param  string  $requestId  操作識別
     * @param  ?string  $id  目標資源
     * @param  ?int  $expectedRevision  版本視圖
     * @param  ?string  $name  傳輸的name欄位，未提供時為null
     * @param  ?string  $projectId  傳輸的projectId欄位，未提供時為null
     * @param  ?array  $selectedIssueIds  傳輸的selectedIssueIds欄位，未提供時為null
     * @param  array<string,mixed>  $original  原始輸入及未知欄位，禁止日誌輸出
     * @param  BoardRuleData|null  $autoInclude  有界自動條件, 缺漏差異保留在原始輸入
     * @param  list<string>|null  $excludedIssueIds  明確排除的純值輸入
     */
    public function __construct(string $operation, string $requestId, ?string $id, ?int $expectedRevision, public ?string $name, public ?string $projectId, public ?array $selectedIssueIds, array $original, public ?BoardRuleData $autoInclude = null, public ?array $excludedIssueIds = null)
    {
        parent::__construct($operation, $requestId, $id, $expectedRevision, $original);
    }
}
