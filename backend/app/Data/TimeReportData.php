<?php

namespace App\Data;

use App\Domain\DomainError;
use App\Domain\Fields;
use DateTimeImmutable;

/** 不可變工時查詢條件；不包含操作者、角色或可信工作區。 */
final readonly class TimeReportData
{
    /** 僅由from建立已驗證的純值，呼叫者不能建立未檢查的查詢條件。 */
    private function __construct(
        public string $startDate,
        public string $endDate,
        public string $projectId,
        public string $userId,
        public ?string $detailGroup,
        public int $detailPage,
        public string $entryGroupKey,
        public int $entryPage,
    ) {}

    /**
     * 正規化HTTP allowlist或既有非HTTP入口，保留日期與整數的原錯誤語意。
     *
     * @param  array  $input  工時範圍及明細條件
     * @return self 已驗證的條件
     */
    public static function from(array $input): self
    {
        $dates = [];
        foreach (['startDate', 'endDate'] as $field) {
            $value = Fields::text($input[$field] ?? '', '分析日期', 10, true);
            $date = preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            if (! $date || $date->format('Y-m-d') !== $value) {
                DomainError::fail('INVALID_INPUT', '請填寫有效的分析日期');
            }
            $dates[] = $value;
        }
        [$start, $end] = $dates;
        if ($start > $end || (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days > 365) {
            DomainError::fail('INVALID_INPUT', '起訖日期需依序且範圍最多366天');
        }
        $group = $input['detailGroup'] ?? null;
        if ($group !== null) {
            Fields::choice($group, ['dates', 'issues'], '明細分組');
        }

        return new self(
            $start, $end,
            Fields::text($input['projectId'] ?? '', '專案', 100),
            Fields::text($input['userId'] ?? '', '成員', 100, $group !== null),
            $group,
            $group === null ? 1 : Fields::integer($input['detailPage'] ?? 1, '明細頁碼', 1, 1000000),
            $group === null ? '' : Fields::text($input['entryGroupKey'] ?? '', '展開群組', 220),
            $group === null ? 1 : Fields::integer($input['entryPage'] ?? 1, '紀錄頁碼', 1, 1000000),
        );
    }

    /** @return array 純聚合器所需的分頁契約，不傳整個查詢或HTTP Request。 */
    public function detailInput(): array
    {
        return ['detailGroup' => $this->detailGroup, 'detailPage' => $this->detailPage, 'entryGroupKey' => $this->entryGroupKey, 'entryPage' => $this->entryPage];
    }
}
