<?php

namespace App\Data;

use App\Domain\DomainError;

/** 專案工時月曆／當日明細的不可變查詢條件，日期皆為Asia/Taipei工作日。 */
readonly class ProjectTimeQueryData
{
    /**
     * 建立已驗證範圍的查詢條件。
     *
     * @param  string  $from  起始工作日（含）
     * @param  string  $to  結束工作日（含）
     * @param  string  $member  指定成員User ID，空字串代表全部可讀成員
     * @param  PageQueryData  $page  當日明細分頁
     */
    public function __construct(public string $from, public string $to, public string $member = '', public PageQueryData $page = new PageQueryData) {}

    /**
     * 由YYYY-MM建立整月範圍。
     *
     * @param  string  $month  月份
     * @param  string  $member  指定成員
     * @return self 月曆條件
     */
    public static function month(string $month, string $member = ''): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $month, $parts) || (int) $parts[2] < 1 || (int) $parts[2] > 12 || (int) $parts[1] < 2000 || (int) $parts[1] > 2100) {
            DomainError::fail('INVALID_INPUT', '月份格式不正確');
        }
        $first = new \DateTimeImmutable($month.'-01');

        return new self($first->format('Y-m-d'), $first->format('Y-m-t'), $member);
    }

    /**
     * 由YYYY-MM-DD建立單日範圍。
     *
     * @param  string  $date  工作日
     * @param  string  $member  指定成員
     * @param  PageQueryData  $page  有界分頁
     * @return self 當日條件
     */
    public static function day(string $date, string $member, PageQueryData $page): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            DomainError::fail('INVALID_INPUT', '日期格式不正確');
        }

        return new self($date, $date, $member, $page);
    }
}
