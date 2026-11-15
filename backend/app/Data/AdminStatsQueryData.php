<?php

namespace App\Data;

/** 管理統計的有界期間與工作區游標 */
final readonly class AdminStatsQueryData
{
    /** 保存已驗證的統計查詢範圍
     *
     * @param  int  $days  僅7或30個日曆日
     * @param  int|null  $before  工作區內部鍵游標, 非租戶授權
     */
    public function __construct(public int $days = 7, public ?int $before = null) {}
}
