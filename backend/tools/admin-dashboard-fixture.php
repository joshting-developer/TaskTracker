<?php

use App\Models\HttpOperationLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Console\Kernel;

/** 僅在指定隔離MySQL追加管理畫面人造資料, 不清空任何庫或改現用帳號 */
if (getenv('APP_ENV') !== 'testing' || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || getenv('DB_DATABASE') !== 'tasktracker_test' || getenv('DB_USERNAME') !== 'tasktracker_test') {
    throw new RuntimeException('Only isolated testing is allowed');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test' || config('admin.username') !== 'isolated_admin') {
    throw new RuntimeException('Only the isolated admin fixture is allowed');
}
if (Workspace::query()->where('public_id', 'not like', '36400000-%')->exists() || User::query()->where('email', '!=', 'admin-visual@example.test')->exists()) {
    throw new RuntimeException('Admin visual baseline needs a fresh synthetic database');
}
User::query()->firstOrCreate(['id' => '36400000-0000-4000-8000-000000000099'], ['name' => '管理驗收者', 'email' => 'admin-visual@example.test', 'password' => password_hash('isolated-visual-only', PASSWORD_BCRYPT), 'email_verified_at' => '2026-11-15 02:00:00']);
for ($i = 1; $i <= 21; $i++) {
    Workspace::query()->firstOrCreate(['public_id' => sprintf('36400000-0000-4000-8000-%012d', $i)], ['name' => '管理驗收工作區 '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'created_at' => '2026-11-15 02:00:00']);
}
$exists = HttpOperationLog::query()->where('request_id', 'tt364-admin-visual-fixture')->exists();
if (! $exists) {
    foreach ([2, 5, 1, 0, 8, 3, 6] as $day => $count) {
        for ($i = 0; $i < $count; $i++) {
            HttpOperationLog::query()->insert(['workspace_public_id' => '36400000-0000-4000-8000-000000000001', 'user_id' => '36400000-0000-4000-8000-000000000099', 'route_name' => ['rpc.createIssue', 'public.issues.comment', 'rpc.logTime'][$i % 3], 'method' => 'POST', 'status' => 200, 'duration_ms' => 1, 'request_id' => 'tt364-admin-visual-fixture', 'occurred_at' => \Carbon\CarbonImmutable::parse('2026-11-15', 'UTC')->subDays(6 - $day)->addHours(2)->format('Y-m-d H:i:s')]);
        }
    }
}
echo "Synthetic admin fixture ready\n";
