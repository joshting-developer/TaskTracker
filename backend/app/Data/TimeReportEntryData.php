<?php

namespace App\Data;

/** 有效工時的查詢投影，不攜帶Model、歷史或未授權的關聯內容。 */
final readonly class TimeReportEntryData
{
    /**
     * @param  string  $id  原始工時公開ID，非資料庫內部主鍵
     * @param  string  $projectId  已限定工作區的專案公開ID
     * @param  string  $userId  工時作者帳號ID
     * @param  string  $issueId  卡片公開ID
     * @param  string  $workDate  台北工作日期YYYY-MM-DD
     * @param  int  $minutes  原始紀錄分鐘數，不含其他卡片彙總
     * @param  string  $note  僅展開查詢才載入的填報說明
     * @param  string|null  $identifier  僅卡片明細才載入的編號
     * @param  string|null  $title  僅卡片明細才載入的名稱
     */
    public function __construct(
        public string $id,
        public string $projectId,
        public string $userId,
        public string $issueId,
        public string $workDate,
        public int $minutes,
        public string $note,
        public ?string $identifier,
        public ?string $title,
    ) {}

    /** @return array 與既有純聚合器相容的明確欄位，非ORM自動序列化。 */
    public function entry(): array
    {
        return ['id' => $this->id, 'projectId' => $this->projectId, 'userId' => $this->userId, 'issueId' => $this->issueId, 'workDate' => $this->workDate, 'minutes' => $this->minutes, 'note' => $this->note];
    }
}
