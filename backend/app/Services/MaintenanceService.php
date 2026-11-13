<?php

namespace App\Services;

use App\Models\HttpOperationLog;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Console\Kernel;

/** 外部短命令的互斥維護入口, 不寄信也不公開HTTP觸發 */
class MaintenanceService
{
    /** 注入有界清理、框架prune命令與共用cache鎖
     * @param  AttachmentCleanupService  $attachments  附件metadata與物件清理
     * @param  Kernel  $console  框架維護命令
     * @param  Factory  $cache  依環境prefix隔離的互斥鎖
     */
    public function __construct(private AttachmentCleanupService $attachments, private Kernel $console, private Factory $cache) {}

    /** 同時只執行一輪, 外部執行者須限制整個程序少於鎖TTL
     * @param  bool  $prune  是否執行每日90天紀錄清除
     * @return array{state:string,purged:int,failed:int,pruneExitCode:int} 不含業務內容的結果
     */
    public function run(bool $prune): array
    {
        $lock = $this->cache->store()->lock('tasktracker:maintenance', 900);
        if (! $lock->get()) {
            return ['state' => 'busy', 'purged' => 0, 'failed' => 0, 'pruneExitCode' => 0];
        }
        try {
            $result = $this->attachments->run();
            $exit = $prune ? $this->console->call('model:prune', ['--model' => [HttpOperationLog::class], '--chunk' => 500]) : 0;

            return ['state' => $result['failed'] || $exit ? 'failed' : 'completed', ...$result, 'pruneExitCode' => $exit];
        } finally {
            $lock->release();
        }
    }
}
