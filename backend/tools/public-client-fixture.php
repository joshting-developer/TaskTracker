<?php

use App\Authorization\ProjectAccess;
use App\Models\PersonalAccessToken;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/** 僅獨立MySQL HTTP驗收調整人造資格；不提供HTTP測試路由。 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test') {
    throw new RuntimeException('Only isolated tasktracker_test is allowed');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$user = User::findOrFail($input['userId']);
if (! str_starts_with($user->email, 'contract-client-') || ! str_ends_with($user->email, '@example.test')) {
    throw new RuntimeException('Not a client acceptance fixture');
}
$member = WorkspaceMember::where('user_id', $user->id)->sole();
$project = Project::where('public_id', $input['projectId'])->where('workspace_id', $member->workspace_id)->sole();
DB::transaction(function () use ($input, $member, $project, $user) {
    switch ($input['action']) {
        case 'maintainer':
        case 'reader':
            $member->update(['role' => 'user', 'disabled_at' => null]);
            ProjectMember::updateOrCreate(['member_id' => $member->id, 'project_id' => $project->id], ['public_id' => ProjectAccess::memberId($project->public_id, $user->id), 'workspace_id' => $member->workspace_id, 'role_id' => $input['action'], 'removed_at' => null]);
            break;
        case 'remove-project':
            ProjectMember::where('member_id', $member->id)->where('project_id', $project->id)->update(['removed_at' => now()]);
            break;
        case 'disable-workspace':
            $member->update(['disabled_at' => now()]);
            break;
        case 'expire-token':
            $token = PersonalAccessToken::whereKey($input['tokenId'])->where('tokenable_id', $user->id)->where('workspace_id', $member->workspace_id)->sole();
            $token->update(['expires_at' => now()->subSecond()]);
            break;
        default:
            throw new RuntimeException('Unknown fixture action');
    }
});
echo "Fixture transition applied\n";
