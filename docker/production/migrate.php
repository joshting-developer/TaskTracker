<?php

// Deployment-only process: credentials arrive on stdin, never in Machine env or HTTP config.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
try {
    $input = json_decode(stream_get_contents(STDIN), true, 8, JSON_THROW_ON_ERROR);
    if (! is_array($input) || ! is_string($input['username'] ?? null) || ! is_string($input['password'] ?? null) || $input['username'] === '' || $input['password'] === '') {
        throw new RuntimeException('Missing migration credentials');
    }
    $app = require '/app/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.url')) {
        throw new RuntimeException('Explicit MySQL connection required');
    }
    config(['database.connections.mysql.username' => $input['username'], 'database.connections.mysql.password' => $input['password']]);
    unset($input);
    DB::purge('mysql');
    exit($kernel->call('migrate', ['--force' => true, '--no-interaction' => true]));
} catch (Throwable) {
    // Neither connection exceptions nor stdin are safe deployment log content.
    fwrite(STDERR, "Migration failed; inspect the protected deployment context\n");
    exit(1);
}
