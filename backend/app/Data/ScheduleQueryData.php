<?php

namespace App\Data;

use App\Domain\DomainError;

/** 時程視圖的不可變日期區間與篩選, 日期為含首尾的計畫日。 */
readonly class ScheduleQueryData
{
    /** 單次查詢最多涵蓋的天數, 足夠月曆六週與一季甘特 */
    public const MAX_DAYS = 92;

    /**
     * 建立已驗證範圍的時程條件。
     *
     * @param  string  $from  起始日（含）
     * @param  string  $to  結束日（含）
     * @param  string  $assignee  承辦者User ID, 空字串不限
     * @param  PageQueryData  $page  有界分頁
     */
    public function __construct(public string $from, public string $to, public string $assignee, public PageQueryData $page) {}

    /**
     * 驗證日期格式、先後與跨度上限。
     *
     * @param  string  $from  起始日
     * @param  string  $to  結束日
     * @param  string  $assignee  承辦者User ID
     * @param  PageQueryData  $page  有界分頁
     * @return self 時程條件
     */
    public static function range(string $from, string $to, string $assignee, PageQueryData $page): self
    {
        foreach ([$from, $to] as $date) {
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                DomainError::fail('INVALID_INPUT', '日期格式不正確');
            }
        }
        $days = (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to));
        if ($days->invert || $days->days + 1 > self::MAX_DAYS) {
            DomainError::fail('INVALID_INPUT', '時程區間最多'.self::MAX_DAYS.'天且結束日不可早於開始日');
        }

        return new self($from, $to, $assignee, $page);
    }
}
