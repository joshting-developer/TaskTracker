<?php

require_once __DIR__.'/isolated-test-connection.php';

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/** 僅隔離管理畫面驗收使用的固定日期router, 不掛載至正式映像或產品路由 */
if (getenv('APP_ENV') !== 'testing' || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || ! isIsolatedTestConnection()) {
    throw new RuntimeException('Only isolated admin visual testing is allowed');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || ! isIsolatedTestConnection(config('database.connections.mysql.database'), config('database.connections.mysql.username'), config('database.connections.mysql.host')) || config('admin.username') !== 'isolated_admin') {
    throw new RuntimeException('Only isolated tasktracker_test is allowed');
}
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-15 '.(new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('H:i:s.u'), 'UTC'));
$app->handleRequest(Request::capture());
