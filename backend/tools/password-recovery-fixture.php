<?php

require_once __DIR__.'/isolated-test-connection.php';

use App\Models\MailDelivery;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// CLI only: never expose a recovery token through a product HTTP endpoint.
if (getenv('APP_ENV') !== 'testing' || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || ! isIsolatedTestConnection()) {
    fwrite(STDERR, "Only isolated recovery fixtures are allowed\n");
    exit(1);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$email = $argv[1] ?? '';
if (! str_ends_with($email, '@example.test')) {
    exit(1);
}
$id = User::where('email', $email)->value('id');
$row = MailDelivery::where('user_id', $id)->where('kind', 'password_reset')->orderByDesc('created_at')->firstOrFail();
preg_match('~/reset-password/([a-f0-9]{64})~', $row->payload['body'], $match);
if (empty($match[1])) {
    exit(1);
}
echo $match[1];
