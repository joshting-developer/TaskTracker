<?php

use App\Domain\DomainError;
use App\Http\ApiExecution;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

// 只給MySqlConcurrencyTest使用，獨立程序／連線模擬不同Session。
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test') {
    throw new RuntimeException('不是隔離MySQL競態環境');
}
$fixture = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$request = Request::create('/api/rpc/'.$fixture['operation'], 'POST');
$user = User::findOrFail($fixture['userId']);
$request->setUserResolver(fn () => $user);
$session = new Store('race', new ArraySessionHandler(120));
$session->start();
$session->put('workspace_id', $fixture['workspaceId']);
$request->setLaravelSession($session);
touch($fixture['ready']);
$deadline = microtime(true) + 10;
while (! is_file($fixture['start'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('競態啟動逾時');
    }
    usleep(10000);
}
try {
    $result = ['ok' => true, 'data' => $app->make(ApiExecution::class)->execute($request, $fixture['operation'], $fixture['input'])];
} catch (DomainError $error) {
    $result = ['ok' => false, 'code' => $error->errorCode];
}
file_put_contents($fixture['result'], json_encode($result, JSON_THROW_ON_ERROR));
