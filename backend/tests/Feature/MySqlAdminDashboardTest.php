<?php

namespace Tests\Feature;

use App\Models\HttpOperationLog;
use App\Repositories\AdminStatisticsRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AdminDashboardCases;
use Tests\MySqlTestCase;

/** 隔離MySQL的日期聚合與有界投影 */
class MySqlAdminDashboardTest extends MySqlTestCase
{
    use AdminDashboardCases, DatabaseTransactions;

    /**
     * 加入無關歷史後驗期間索引及固定聚合查詢數, 不以SQLite代替目標引擎
     *
     * @return void
     */
    public function test_admin_date_range_uses_existing_index_and_does_not_load_history(): void
    {
        $row = ['workspace_public_id' => 'isolated-statistics', 'user_id' => 'isolated-user', 'route_name' => 'rpc.createIssue', 'method' => 'POST', 'status' => 200, 'duration_ms' => 1, 'occurred_at' => '2020-01-01 00:00:00'];
        HttpOperationLog::insert(array_fill(0, 1000, $row));
        HttpOperationLog::insert([...$row, 'occurred_at' => '2030-11-15 10:00:00']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = $this->app->make(AdminStatisticsRepository::class)->summary('2030-11-09 00:00:00', '2030-11-16 00:00:00');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(4, $queries);
        $this->assertSame(1, $result->active['requests']);
        $this->assertCount(1, $result->daily);
        foreach (array_slice($queries, 0, 2) as $query) {
            $plan = DB::select('EXPLAIN '.$query['query'], $query['bindings']);
            $this->assertSame('range', $plan[0]->type);
            $this->assertSame('http_operation_logs_retention', $plan[0]->key);
            $this->assertLessThan(100, (int) $plan[0]->rows);
        }
    }
}
