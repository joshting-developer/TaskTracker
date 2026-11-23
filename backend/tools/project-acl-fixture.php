<?php

require_once __DIR__.'/isolated-test-connection.php';
if (! isIsolatedTestConnection()) {
    throw new RuntimeException('Only isolated ACL fixtures are allowed');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || ! isIsolatedTestConnection(Illuminate\Support\Facades\DB::getDatabaseName())) {
    throw new RuntimeException('Isolated MySQL fixture only');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! in_array($input['count'] ?? 25, [21, 25], true)) {
    throw new RuntimeException('Unsupported isolated member count');
}
$users = Illuminate\Support\Facades\DB::transaction(function () use ($input): array {
    $workspace = Illuminate\Support\Facades\DB::table('workspaces')->where('public_id', $input['workspaceId'])->value('id');
    if (! $workspace || ! Illuminate\Support\Facades\DB::table('workspace_members')->where('workspace_id', $workspace)->where('user_id', $input['userId'])->where('role', 'owner')->exists()) {
        throw new RuntimeException('Synthetic fixture owner mismatch');
    }
    // Existing over-capacity workspaces retain management and paginated reads.
    $users = [];
    for ($index = 0; $index < ($input['count'] ?? 25); $index++) {
        $user = App\Models\User::factory()->create([
            'id' => (string) Illuminate\Support\Str::uuid(),
            'name' => sprintf('Person %03d', $index),
            'email' => 'acl-'.Illuminate\Support\Str::uuid().'@example.test',
            'password' => 'test-local-password',
        ]);
        App\Models\WorkspaceMember::create(['workspace_id' => $workspace, 'user_id' => $user->id, 'role' => 'user', 'joined_at' => now()]);
        $users[] = ['id' => $user->id, 'email' => $user->email];
    }
    return $users;
});
echo json_encode($users, JSON_THROW_ON_ERROR);
