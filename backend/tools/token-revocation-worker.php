<?php

use App\Database\WriteLocks;
use App\Domain\DomainError;
use App\Http\Integrations\BearerTokenSession;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

// 只供獨立MySQL競態測試；fixture內人造Token不輸出，結束由測試清理。
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test') {
    throw new RuntimeException('不是隔離Token競態環境');
}
$fixture = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$app->instance(WriteLocks::class, new class($fixture['ready']) extends WriteLocks
{
    /** 記錄已完成鎖前hint查詢，接著進入真實InnoDB鎖。 */
    public function __construct(private string $ready) {}

    /**
     * 在取鎖前通知主程序；不繞過原有帳號鎖。
     *
     * @param  string|null  $userId  可信Token對應帳號
     * @return void
     */
    public function account(?string $userId): void
    {
        touch($this->ready);
        parent::account($userId);
    }
});
$request = Request::create('/api/public/test', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$fixture['plain']]);
try {
    $app->make(BearerTokenSession::class)->write($request, 'test.revocation', fn () => 'must not run', $fixture['project']);
    $result = ['ok' => true];
} catch (DomainError $e) {
    $result = ['ok' => false, 'code' => $e->errorCode];
}
file_put_contents($fixture['result'], json_encode($result, JSON_THROW_ON_ERROR));
