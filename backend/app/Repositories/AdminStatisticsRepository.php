<?php

namespace App\Repositories;

use App\Data\AdminStatisticsData;
use App\Models\HttpOperationLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** 平台管理的彙總資料入口, 不選業務內容或讀取整個Workspace */
class AdminStatisticsRepository
{
    /**
     * 以既有occurred_at索引聚合最多30日的成功寫入紀錄
     *
     * @param  string  $from  UTC含起點
     * @param  string  $until  UTC不含終點
     * @return AdminStatisticsData 固定大小總計及最多30列趨勢
     */
    public function summary(string $from, string $until): AdminStatisticsData
    {
        $active = $this->activity($from, $until)->selectRaw('COUNT(DISTINCT user_id) AS users, COUNT(DISTINCT workspace_public_id) AS workspaces, COUNT(*) AS requests')->first();
        // DATE使用UTC, 避免依賴MySQL時區表或逐筆載入PHP
        $daily = $this->activity($from, $until)->selectRaw("DATE(occurred_at) AS day, COUNT(*) AS requests, SUM(CASE WHEN route_name IN ('rpc.createIssue','public.issues.create') THEN 1 ELSE 0 END) AS issues, SUM(CASE WHEN route_name IN ('rpc.addComment','public.issues.comment') THEN 1 ELSE 0 END) AS comments, SUM(CASE WHEN route_name = 'rpc.logTime' THEN 1 ELSE 0 END) AS time_entries")
            ->groupByRaw('DATE(occurred_at)')->orderBy('day')->limit(30)->get();

        return new AdminStatisticsData(
            ['users' => User::query()->count(), 'workspaces' => Workspace::query()->count()],
            ['users' => (int) $active->users, 'workspaces' => (int) $active->workspaces, 'requests' => (int) $active->requests],
            $daily->map(fn ($row) => ['date' => (string) $row->day, 'requests' => (int) $row->requests, 'issues' => (int) $row->issues, 'comments' => (int) $row->comments, 'timeEntries' => (int) $row->time_entries])->all(),
        );
    }

    /**
     * 只取名稱、公開ID與建立時間, 21筆判斷下一頁, 不逐工作區COUNT
     *
     * @param  int|null  $before  前頁最後內部鍵
     * @return Collection<int,Workspace> 最多21筆必要識別
     */
    public function workspaces(?int $before): Collection
    {
        return Workspace::query()->when($before !== null, fn ($query) => $query->where('id', '<', $before))
            ->orderByDesc('id')->limit(21)->get(['id', 'public_id', 'name', 'created_at']);
    }

    /**
     * 限定具身分、空間且成功的寫入, 排除驗證及管理操作避免自我增加活躍
     *
     * @param  string  $from  UTC含起點
     * @param  string  $until  UTC不含終點
     * @return Builder<HttpOperationLog> Repository內執行, 不外洩Builder
     */
    private function activity(string $from, string $until): Builder
    {
        return HttpOperationLog::query()->where('occurred_at', '>=', $from)->where('occurred_at', '<', $until)
            ->whereBetween('status', [200, 299])->whereNotNull('user_id')->whereNotNull('workspace_public_id')
            ->whereNotIn('method', ['GET', 'HEAD', 'OPTIONS'])->where('route_name', 'not like', 'auth.%')->where('route_name', 'not like', 'admin.%');
    }
}
