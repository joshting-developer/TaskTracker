<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\RegistersVerifiedFixtures;

require_once __DIR__.'/../tools/isolated-test-connection.php';

/** 只接受獨立Compose測試帳號，預設測試仍由TestCase強制memory SQLite。 */
abstract class MySqlTestCase extends BaseTestCase
{
    use RegistersVerifiedFixtures;

    protected string $testDatabase = 'tasktracker_test';

    /**
     * 未開啟獨立MySQL測試旗標時略過，避免預設測試連到MySQL。
     *
     * @return void
     */
    protected function setUp(): void
    {
        if (getenv('TASKTRACKER_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('請使用獨立MySQL測試Compose');
        }
        parent::setUp();
    }

    /**
     * JSON物件key順序不屬於契約；保留list順序及所有值的嚴格型別比較。
     *
     * @param  array  $expected  預期DTO
     * @param  array  $actual  實際DTO
     * @return void
     */
    protected function assertDtoSame(array $expected, array $actual): void
    {
        $normalize = function ($value) use (&$normalize) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        };
        $this->assertSame($normalize($expected), $normalize($actual));
    }

    /**
     * 只接受目前Agent的共用MySQL專用帳號與允許的schema，強制改用隔離MySQL連線後建立應用程式。
     *
     * @return Application 測試用應用程式
     */
    public function createApplication()
    {
        if (! in_array($this->testDatabase, ['tasktracker_test', 'tasktracker_test_import'], true)) {
            throw new \RuntimeException('不允許的測試資料庫');
        }
        if (getenv('TASKTRACKER_MYSQL_TEST') !== '1') {
            throw new \RuntimeException('不是隔離MySQL帳號');
        }
        // Preserve both credentials before replacing the application's DB_PASSWORD_FILE.
        $passwordFile = getenv('DB_PASSWORD_FILE');
        if ($passwordFile && is_file($passwordFile)) {
            foreach (['TEST', 'IMPORT'] as $target) {
                $key = 'TASKTRACKER_'.$target.'_PASSWORD';
                if (! getenv($key)) {
                    $value = trim(file_get_contents($passwordFile));
                    putenv($key.'='.$value);
                    $_ENV[$key] = $_SERVER[$key] = $value;
                }
            }
        }
        $purpose = $this->testDatabase === 'tasktracker_test_import' ? 'IMPORT' : 'TEST';
        $database = getenv('TASKTRACKER_'.$purpose.'_DATABASE') ?: '';
        $username = getenv('TASKTRACKER_'.$purpose.'_USERNAME') ?: '';
        $password = getenv('TASKTRACKER_'.$purpose.'_PASSWORD') ?: (getenv('DB_PASSWORD_FILE') && is_file(getenv('DB_PASSWORD_FILE')) ? trim(file_get_contents(getenv('DB_PASSWORD_FILE'))) : '');
        $host = getenv('TASKTRACKER_STANDALONE_TEST') === '1' ? 'mysql' : 'side-project-mysql';
        if (! isIsolatedTestConnection($database, $username, $host)) {
            throw new \RuntimeException('不是目前 Agent 的共用隔離測試庫');
        }
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password, 'DB_PASSWORD_FILE' => '', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_DELIVERY_MODE' => 'queue', 'BROADCAST_CONNECTION' => 'null'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = parent::createApplication();
        if ($app['db']->connection()->getDatabaseName() !== $database || $app['db']->connection()->getDriverName() !== 'mysql') {
            throw new \RuntimeException('MySQL測試未隔離');
        }

        return $app;
    }
}
