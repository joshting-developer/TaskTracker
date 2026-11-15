<?php

namespace App\Services;

use App\Data\AdminStatsQueryData;
use App\Repositories\AdminStatisticsRepository;
use Carbon\CarbonImmutable;

/** 管理統計的資格重驗、固定UTC期間及補零規則 */
class AdminStatisticsService
{
    /** 注入管理資格與統計資料入口, 不在建構時取得操作者
     *
     * @param  AdminAuthenticationService  $authentication  當次資格重驗
     * @param  AdminStatisticsRepository  $statistics  有界統計資料入口
     */
    public function __construct(private AdminAuthenticationService $authentication, private AdminStatisticsRepository $statistics) {}

    /**
     * 重驗資格後讀取全站總計及期間聚合, 零活動日期明確補0
     *
     * @param  mixed  $claim  可信Session資格
     * @param  AdminStatsQueryData  $query  驗證過的固定期間
     * @return array<string,mixed> 固定欄位及最多30筆趨勢
     */
    public function summary(mixed $claim, AdminStatsQueryData $query): array
    {
        $this->authentication->assertCurrent($claim);
        $until = CarbonImmutable::now('UTC')->startOfDay()->addDay();
        $from = $until->subDays($query->days);
        $data = $this->statistics->summary($from->format('Y-m-d H:i:s'), $until->format('Y-m-d H:i:s'));
        $byDate = array_column($data->daily, null, 'date');
        $daily = [];
        for ($i = 0; $i < $query->days; $i++) {
            $date = $from->addDays($i)->format('Y-m-d');
            $daily[] = $byDate[$date] ?? ['date' => $date, 'requests' => 0, 'issues' => 0, 'comments' => 0, 'timeEntries' => 0];
        }

        return ['totals' => $data->totals, 'active' => $data->active, 'daily' => $daily,
            'period' => ['days' => $query->days, 'from' => $from->toDateString(), 'through' => $until->subDay()->toDateString(), 'timezone' => 'UTC'],
            'definition' => 'successful_workspace_write_requests'];
    }

    /**
     * 重驗管理資格後讀必要工作區識別, 不讀成員與內容
     *
     * @param  mixed  $claim  可信Session資格
     * @param  AdminStatsQueryData  $query  有界游標
     * @return array{items:list<array{id:string,name:string,createdAt:?string}>,nextBefore:?string} 最多20筆
     */
    public function workspaces(mixed $claim, AdminStatsQueryData $query): array
    {
        $this->authentication->assertCurrent($claim);
        $rows = $this->statistics->workspaces($query->before);
        $more = $rows->count() > 20;
        $page = $rows->take(20);

        return ['items' => $page->map(fn ($row) => ['id' => $row->public_id, 'name' => $row->name, 'createdAt' => $row->created_at])->values()->all(),
            'nextBefore' => $more ? (string) $page->last()->id : null];
    }
}
