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

// 信件預設在請求commit後交付, 不依賴每分鐘排程或常駐worker。
