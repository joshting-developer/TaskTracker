<?php

namespace App\Data;

/** 已在SQL完成聚合的管理統計, 不含使用者業務內文 */
final readonly class AdminStatisticsData
{
    /** 保存SQL已聚合的必要統計
     *
     * @param  array<string,int>  $totals  全站帳號及工作區數
     * @param  array{users:int,workspaces:int,requests:int}  $active  期間成功寫入的去重身分及請求數
     * @param  list<array{date:string,requests:int,issues:int,comments:int,timeEntries:int}>  $daily  SQL最多30列, 尚未補零
     */
    public function __construct(public array $totals, public array $active, public array $daily) {}
}
