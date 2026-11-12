<?php

namespace App\Data\Writes;

/**
 * 卡片寫入（建立／修改／留言）的型別化輸入。業務驗證仍由Fields與IssueService負責；
 * 原始輸入完整保留給RequestReplay計算簽章，未知欄位不丟棄，同requestId重試的簽章不變。
 */
final readonly class IssueWriteData
{
    /**
     * 只由IssueWriteRequest以已通過型別檢查的輸入建立。
     *
     * @param  string  $operation  createIssue、updateIssue或addComment
     * @param  string  $requestId  防重複提交識別碼
     * @param  string|null  $id  卡片公開ID（修改／留言）
     * @param  int|null  $expectedRevision  修改／留言的基準revision
     * @param  string|null  $projectId  建立時的Project公開ID
     * @param  string|null  $boardId  建立時同交易收錄的看板
     * @param  string|null  $content  留言內容
     * @param  array<string,mixed>  $fields  有提供的卡片欄位（title、state等），未提供的不出現
     * @param  array<string,mixed>  $input  原始輸入，供簽章與領域服務使用
     */
    public function __construct(
        public string $operation,
        public string $requestId,
        public ?string $id,
        public ?int $expectedRevision,
        public ?string $projectId,
        public ?string $boardId,
        public ?string $content,
        public array $fields,
        private array $input,
    ) {}

    /**
     * 交給既有RPC用例的輸入；與HTTP原始輸入完全相同。
     *
     * @return array<string,mixed> 原始輸入
     */
    public function input(): array
    {
        return $this->input;
    }
}
