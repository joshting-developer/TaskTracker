<?php

use App\Models\HttpOperationLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TT-260：操作紀錄保存90天；需有scheduler執行者（見TT-162）才會自動清除。
Schedule::command('model:prune', ['--model' => [HttpOperationLog::class]])->daily();

Schedule::command('attachments:cleanup')->hourly()->withoutOverlapping();

// 部署需啟用scheduler；broker中斷時保留outbox, 恢復後每輪最多100筆。
Schedule::command('mail:dispatch')->everyMinute()->withoutOverlapping();
