<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/** 提供具明確reset選項的CLI入口，資料建立由DemoSeeder負責。 */
class SeedDemo extends Command
{
    protected $signature = 'tasktracker:seed-demo {--reset : 只重設 seed 的 f-ws 工作空間, 不修改其他空間}';
    protected $description = '建立四種權限帳號／f-ws／first／second 與子任務、關聯及工時示範資料';

    /** 呼叫受環境限制的Seeder並回報結果，不在Command保存示範資料。
     * @param DemoSeeder $seeder 集中示範資料與環境驗證的Seeder
     * @return int 程序退出碼
     */
    public function handle(DemoSeeder $seeder): int
    {
        try { $result = $seeder->seed((bool) $this->option('reset')); }
        catch (\Throwable $error) { $this->error($error->getMessage()); return self::FAILURE; }
        $this->info($result['created'] ? '示範資料已建立：f-ws → first／second' : ($result['upgraded'] ? '示範資料已補齊，保留原有帳號、專案與工時' : '示範資料已存在, 保留目前修改；需要還原請加 --reset'));
        foreach (DemoSeeder::ACCOUNTS as $role => $account) $this->line($role.'：'.$account['email']);
        $this->line('共用密碼使用本機 TASKTRACKER_SEED_PASSWORD 設定');
        return self::SUCCESS;
    }
}
