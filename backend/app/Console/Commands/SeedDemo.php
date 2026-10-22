<?php

namespace App\Console\Commands;

use App\Services\DemoData;
use Illuminate\Console\Command;

class SeedDemo extends Command
{
    protected $signature = 'tasktracker:seed-demo {--reset : 只重設 seed 的 f-ws 工作空間, 不修改其他空間}';
    protected $description = '建立四種權限帳號／f-ws／first／second 與子任務、關聯及工時示範資料';

    public function handle(DemoData $data): int
    {
        try { $result = $data->seed((bool) $this->option('reset')); }
        catch (\Throwable $error) { $this->error($error->getMessage()); return self::FAILURE; }
        $this->info($result['created'] ? '示範資料已建立：f-ws → first／second' : ($result['upgraded'] ? '已加入四個權限帳號, 保留原有專案與任務' : '示範資料已存在, 保留目前修改；需要還原請加 --reset'));
        foreach (DemoData::ACCOUNTS as $role => $account) $this->line($role.'：'.$account['email']);
        $this->line('共用密碼使用本機 TASKTRACKER_SEED_PASSWORD 設定');
        return self::SUCCESS;
    }
}
