<?php

use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Console\Kernel;

// Only synthetic isolated fixtures; no product HTTP endpoint or production database access.
if (getenv('APP_ENV') !== 'testing' || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || getenv('DB_DATABASE') !== 'tasktracker_test' || getenv('DB_USERNAME') !== 'tasktracker_test') {
    fwrite(STDERR, "Only isolated detached fixtures are allowed\n");
    exit(1);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$email = $argv[1] ?? '';
if (! str_ends_with($email, '@example.test')) {
    exit(1);
}
$user = User::where('email', $email)->firstOrFail();
WorkspaceMember::where('user_id', $user->id)->update(['disabled_at' => now()]);
echo "Detached synthetic account\n";
