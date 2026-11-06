<?php

namespace App\Data;

/** 有效工時的查詢投影，不攜帶Model、歷史或未授權的關聯內容。 */
final readonly class TimeReportEntryData
{
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
