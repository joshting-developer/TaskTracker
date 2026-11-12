<?php

use App\Data\WorkspaceContext;
use App\Http\WorkspaceRpc;
use App\Http\WorkspaceTransaction;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** 僅供隔離原生MySQL多worker測試的HTTP router；不掛載至正式路由。 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! $app->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test' || ! getenv('TASKTRACKER_SESSION_RACE_PREFIX')) {
    throw new RuntimeException('不是隔離Session競態環境');
}
Route::post('/api/test/session-slow', function (Request $request) use ($app) {
    $input = $request->json()->all();
    touch(getenv('TASKTRACKER_SESSION_RACE_PREFIX').'.ready');
    usleep(12000000);
    $data = ($input['mode'] ?? '') === 'save'
        ? $app->make(WorkspaceRpc::class)->execute($request, 'updateIssue', $input['input'])
        : $app->make(WorkspaceTransaction::class)->read($request, fn (WorkspaceContext $context) => ['workspaceId' => $context->workspaceId]);

    return response()->json(['ok' => true, 'data' => $data]);
})->middleware('web')->block(10, 10);
Route::post('/api/test/session-wait', function () {
    touch(getenv('TASKTRACKER_SESSION_RACE_PREFIX').'.unexpected');

    return response()->json(['ok' => true]);
})->middleware('web')->block(10, 0);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
