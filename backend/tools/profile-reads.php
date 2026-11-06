<?php

use App\Authorization\WorkspacePermissions;
use App\Models\User;
use App\Repositories\RecordStore;
use App\Repositories\TimeReportRepository;
use App\Services\WorkspaceQuery;
use App\Services\WorkspaceTimeReport;
use Database\Seeders\DemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * 隔離讀取效能取樣：只使用程序內SQLite與人造seed，拒絕沿用容器資料庫。
 * 使用：docker compose exec -T backend php < backend/tools/profile-reads.php
 * 不測HTTP／Session耗時、不做併發壓測；SQL計數包含repository及授權成本。
 */
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'null'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'cache.default' => 'array', 'queue.default' => 'sync', 'broadcasting.default' => 'null']);
DB::purge('sqlite');
if (! $app->environment('testing') || config('database.connections.sqlite.database') !== ':memory:' || DB::selectOne('PRAGMA database_list')->file !== '') {
    throw new RuntimeException('未確認記憶體隔離，拒絕執行');
}
Artisan::call('migrate', ['--force' => true]);
$app->make(DemoSeeder::class)->seed(password: bin2hex(random_bytes(16)));

/**
 * 取樣單次完整呼叫，避免保留query bindings或業務內容於報告。
 *
 * @param  string  $name  可比較的場景名稱
 * @param  Closure  $read  完整讀取用例
 * @return void
 */
function sample(string $name, Closure $read): void
{
    DB::enableQueryLog();
    DB::flushQueryLog();
    $start = hrtime(true);
    $result = $read();
    $elapsed = (hrtime(true) - $start) / 1e6;
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $shapes = [];
    foreach ($queries as $query) {
        $shapes[$query['query']] = ($shapes[$query['query']] ?? 0) + 1;
    }
    arsort($shapes);
    echo json_encode(['scenario' => $name, 'queries' => count($queries), 'elapsedMs' => round($elapsed, 2), 'jsonBytes' => strlen(json_encode($result)), 'topSqlShapes' => array_slice($shapes, 0, 4, true)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
}

$context = [
    'user' => ['id' => DemoSeeder::USER_ID, 'name' => 'owner'],
    'workspace' => ['id' => DemoSeeder::WORKSPACE_ID, 'name' => 'f-ws'],
    'role' => 'owner',
    'permissions' => WorkspacePermissions::resolve('owner'),
];
$store = fn () => (new RecordStore)->forWorkspace(DemoSeeder::WORKSPACE_ID);
$workspace = fn () => (new WorkspaceQuery($store(), $context))->getWorkspace();
$report = fn () => (new WorkspaceTimeReport($store(), $context, new TimeReportRepository))->get(['startDate' => '2025-01-01', 'endDate' => '2025-12-31']);
echo json_encode(['baselineEntries' => DB::table('time_entries')->count(), 'baselineIssues' => DB::table('issues')->count()], JSON_THROW_ON_ERROR).PHP_EOL;
sample('seed.workspace', $workspace);
sample('seed.report2025', $report);
sample('seed.issuesOnly', fn () => $store()->all('issues'));
$source = (array) DB::table('time_entries')->first();
unset($source['id']);
for ($index = 0; $index < 200; $index++) {
    $date = (new DateTimeImmutable('2025-01-01'))->modify('+'.$index.' days')->format('Y-m-d');
    DB::table('time_entries')->insert([...$source, 'public_id' => 'profile-entry-'.$index, 'work_date' => $date, 'minutes' => 15, 'note' => '', 'voided_at' => null]);
    if (in_array($index, [49, 199], true)) {
        sample('plus'.($index + 1).'.workspace', $workspace);
        sample('plus'.($index + 1).'.report2025', $report);
    }
}
for ($index = 0; $index < 20; $index++) {
    $user = User::create(['id' => 'profile-user-'.$index, 'name' => 'profile'.$index, 'email' => 'profile'.$index.'@example.test', 'password' => bin2hex(random_bytes(16))]);
    $store()->put('members', $user->id, ['id' => $user->id, 'role' => 'user', 'joinedAt' => now()->utc()->toISOString()]);
}
sample('plus200.24members.workspace', $workspace);
sample('plus200.24members.report2025', $report);
echo json_encode(['isolation' => 'sqlite::memory:', 'projects' => DB::table('projects')->count(), 'members' => DB::table('workspace_members')->count(), 'entries' => DB::table('time_entries')->count()], JSON_THROW_ON_ERROR).PHP_EOL;

$issue = (array) DB::table('issues')->where('type', 'task')->first();
unset($issue['id']);
for ($index = 0; $index < 100; $index++) {
    DB::table('issues')->insert([...$issue, 'public_id' => 'profile-issue-'.$index, 'identifier' => 'PROFILE-'.($index + 1), 'storage_position' => 1000 + $index, 'parent_issue_id' => null, 'epic_id' => null]);
}
sample('plus100.issuesOnly', fn () => $store()->all('issues'));
