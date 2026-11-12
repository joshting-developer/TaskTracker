<?php

use App\Factories\SessionLockFactory;
use Illuminate\Contracts\Console\Kernel;

/** 隔離測例持鎖程序；刻意等待父程序終止，驗證連線關閉自動釋放。 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test') {
    throw new RuntimeException('不是隔離Session競態環境');
}
$lease = $app->make(SessionLockFactory::class)->acquire('test', $argv[1], 1);
$lease->assertHeld();
touch($argv[1].'.ready');
sleep(30);
