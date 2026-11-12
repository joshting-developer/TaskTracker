<?php

namespace App\Data\Writes;

/**
 * 工時寫入（填報／修改／作廢）的型別化輸入。每日上限、作者與工作日規則仍由TimeEntryService負責；
 * 原始輸入完整保留給RequestReplay計算簽章。
 */
final readonly class TimeEntryWriteData
{
    /**
     * 只由TimeEntryWriteRequest以已通過型別檢查的輸入建立。
     *
     * @param  string  $operation  logTime、updateTime或voidTime
     * @param  string  $requestId  防重複提交識別碼
     * @param  string|null  $id  工時公開ID（修改／作廢）
     * @param  int|null  $expectedRevision  修改／作廢的基準revision
     * @param  string|null  $issueId  卡片公開ID
     * @param  string|null  $workDate  工作日（Asia/Taipei，YYYY-MM-DD）
     * @param  int|null  $minutes  投入分鐘
     * @param  string|null  $note  工時說明
     * @param  array<string,mixed>  $input  原始輸入，供簽章與領域服務使用
     */
    public function __construct(
        public string $operation,
        public string $requestId,
        public ?string $id,
        public ?int $expectedRevision,
        public ?string $issueId,
        public ?string $workDate,
        public ?int $minutes,
        public ?string $note,
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
