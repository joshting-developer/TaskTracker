<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/** 固定人造視覺fixture排序；僅CLI獨立測試庫，不提供產品HTTP入口。 */
if (getenv('APP_ENV') !== 'testing' || getenv('TASKTRACKER_MYSQL_TEST') !== '1') {
    throw new RuntimeException('Only isolated visual testing is allowed');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test') {
    throw new RuntimeException('Only isolated tasktracker_test is allowed');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$user = User::findOrFail($input['userId']);
if (! str_starts_with($user->email, 'visual-') || ! str_ends_with($user->email, '@example.test')) {
    throw new RuntimeException('Not a synthetic visual fixture');
}
$workspace = Workspace::where('public_id', $input['workspaceId'])->where('name', '產品工作區')->sole();
WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $user->id)->whereNull('disabled_at')->sole();
$project = Project::where('workspace_id', $workspace->id)->where('public_id', $input['projectId'])->where('code', 'VRT')->sole();
$empty = Project::where('workspace_id', $workspace->id)->where('public_id', $input['emptyId'])->where('code', 'EMPTY')->sole();
DB::transaction(function () use ($project, $empty) {
    $project->update(['created_at' => '2026-11-13 03:00:00', 'updated_at' => '2026-11-13 04:00:00']);
    $empty->update(['created_at' => '2026-11-13 02:00:00', 'updated_at' => '2026-11-13 03:00:00']);
    // TT-302：詳細頁會顯示建立時間與最後異動時間；固定成同一分鐘內、照原本先後各差一秒, 不改任何排序。
    $issues = DB::table('issues')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->orderBy('created_at')->orderBy('id')->pluck('id');
    foreach ($issues as $index => $id) {
        DB::table('issues')->where('id', $id)->update(['created_at' => sprintf('2026-11-13 03:30:%02d', $index)]);
    }
    DB::table('audit_events')->where('workspace_id', $project->workspace_id)->where('entity_type', 'issues')->where('stream', 'history')->whereIn('entity_id', $issues)
        ->update(['event_data' => DB::raw("JSON_SET(event_data, '$.at', '2026-11-13T03:30:00.000000Z')")]);
});
echo "Synthetic visual clock fixed\n";
