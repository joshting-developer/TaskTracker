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
DB::transaction(function () use ($project, $empty, $input) {
    $notes = DB::table('todos')->where('workspace_id', $project->workspace_id)->orderBy('id')->limit(100)->pluck('id');
    foreach ($notes as $index => $noteId) {
        $at = (new DateTimeImmutable('2026-11-13 03:00:00'))->modify('+'.$index.' seconds')->format('Y-m-d H:i:s');
        DB::table('todos')->where('id', $noteId)->update(['created_at' => $at, 'updated_at' => $at]);
    }
    DB::table('todo_comments')->where('workspace_id', $project->workspace_id)->update(['created_at' => '2026-11-13 04:00:00']);
    $project->update(['created_at' => '2026-11-13 03:00:00', 'updated_at' => '2026-11-13 04:00:00']);
    $empty->update(['created_at' => '2026-11-13 02:00:00', 'updated_at' => '2026-11-13 03:00:00']);
    if (! empty($input['normalizeInvitations'])) {
        if (! in_array($input['profile'] ?? '', ['desktop', 'tablet', 'mobile'], true)) throw new RuntimeException('Invalid synthetic visual profile');
        // Only this guarded synthetic workspace is normalized; no business or global account changes.
        $users = DB::table('workspace_members as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.workspace_id', $project->workspace_id)->select('u.id', 'u.name')->get();
        foreach ($users as $member) {
            $label = $member->name === '視覺驗收者' ? 'owner' : 'collaborator';
            DB::table('users')->where('id', $member->id)->update(['email' => 'visual-'.$label.'-'.$input['profile'].'@example.test']);
        }
        DB::table('workspace_invitations')->where('workspace_id', $project->workspace_id)->where('email', 'pending-visual@example.test')->whereNull('consumed_at')->update(['expires_at' => '2030-11-20 04:00:00']);
    }
    // TT-302：詳細頁會顯示建立時間與最後異動時間；固定成同一分鐘內、照原本先後各差一秒, 不改任何排序。
    $issues = DB::table('issues')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->orderBy('created_at')->orderBy('id')->pluck('id');
    foreach ($issues as $index => $id) {
        DB::table('issues')->where('id', $id)->update(['created_at' => sprintf('2026-11-13 03:30:%02d', $index)]);
    }
    DB::table('audit_events')->where('workspace_id', $project->workspace_id)->where('entity_type', 'issues')->where('stream', 'history')->whereIn('entity_id', $issues)
        ->update(['event_data' => DB::raw("JSON_SET(event_data, '$.at', '2026-11-13T03:30:00.000000Z')")]);
});
echo "Synthetic visual clock fixed\n";
