<?php

namespace App\Console\Commands;

use App\Services\MaintenanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** 外部每小時觸發, 每日其中一輪帶--prune；不需要常駐scheduler */
class RunMaintenance extends Command
{
    protected $signature = 'tasktracker:maintenance {--prune : Also prune operation logs older than 90 days}';

    protected $description = 'Run bounded attachment cleanup and optional operation log pruning';

    /** 框架生命週期注入, 輸出結果供外部排程判斷失敗
     * @param  MaintenanceService  $maintenance  互斥維護用例
     * @return int 非0表示失敗或已有工作執行
     */
    public function handle(MaintenanceService $maintenance): int
    {
        $started = microtime(true);
        try {
            $result = $maintenance->run((bool) $this->option('prune'));
            $result['durationMs'] = (int) round((microtime(true) - $started) * 1000);
            Log::info('TaskTracker maintenance finished', $result);
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));

            return $result['state'] === 'completed' ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable) {
            Log::error('TaskTracker maintenance failed');
            $this->error('Maintenance failed; inspect service health and retry');

            return self::FAILURE;
        }
    }
}
