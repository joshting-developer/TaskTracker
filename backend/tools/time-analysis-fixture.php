<?php

// Diagnostic fixture only: deterministic project IDs keep chart colour ordering stable.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || getenv('DB_USERNAME') !== 'tasktracker_test' || Illuminate\Support\Facades\DB::getDatabaseName() !== 'tasktracker_test') {
    throw new RuntimeException('Isolated MySQL fixture only');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
Illuminate\Support\Facades\DB::transaction(function () use ($input) {
    $workspace = Illuminate\Support\Facades\DB::table('workspaces')->where('public_id', $input['workspaceId'])->value('id');
    if (! $workspace || ! Illuminate\Support\Facades\DB::table('workspace_members')->where('workspace_id', $workspace)->where('user_id', $input['userId'])->where('role', 'owner')->exists()) {
        throw new RuntimeException('Synthetic fixture owner mismatch');
    }
    foreach (['first' => 'analysis-2', 'second' => 'analysis-1'] as $field => $publicId) {
        $updated = Illuminate\Support\Facades\DB::table('projects')->where('workspace_id', $workspace)->where('public_id', $input[$field])->update(['public_id' => $publicId]);
        if ($updated !== 1) {
            throw new RuntimeException('Synthetic project mismatch');
        }
    }
});
