<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\RegistersVerifiedFixtures;

require_once __DIR__.'/../tools/isolated-test-connection.php';

/** 只接受明確配置的隔離 MySQL 測試庫與專用帳號。 */
abstract class TestCase extends BaseTestCase
{
    use RegistersVerifiedFixtures;

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
        if (getenv('TASKTRACKER_MYSQL_TEST') !== '1') {
            throw new \RuntimeException('不是隔離MySQL帳號');
        }
        $passwordFile = getenv('DB_PASSWORD_FILE');
        $password = getenv('TASKTRACKER_TEST_PASSWORD') ?: ($passwordFile && is_file($passwordFile) ? trim(file_get_contents($passwordFile)) : '');
        // 重新建立應用程式時, 密碼檔環境已被清掉, 仍需保留專用測試密碼。
        putenv('TASKTRACKER_TEST_PASSWORD='.$password);
        $_ENV['TASKTRACKER_TEST_PASSWORD'] = $_SERVER['TASKTRACKER_TEST_PASSWORD'] = $password;
        $database = getenv('TASKTRACKER_TEST_DATABASE') ?: '';
        $username = getenv('TASKTRACKER_TEST_USERNAME') ?: '';
        $host = getenv('TASKTRACKER_STANDALONE_TEST') === '1' ? 'mysql' : 'side-project-mysql';
        if (! isIsolatedTestConnection($database, $username, $host)) {
            throw new \RuntimeException('不是目前 Agent 的共用隔離測試庫');
        }
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password, 'DB_PASSWORD_FILE' => '', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_DELIVERY_MODE' => 'queue', 'BROADCAST_CONNECTION' => 'null'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = parent::createApplication();
        if (! $app->environment('testing')
            || $app['config']->get('database.connections.mysql.database') !== $database
            || $app['config']->get('database.connections.mysql.username') !== $username
            || $app['config']->get('database.default') !== 'mysql'
            || ! isIsolatedTestConnection($app['config']->get('database.connections.mysql.database'), $app['config']->get('database.connections.mysql.username'), $app['config']->get('database.connections.mysql.host'))
            || $app['config']->get('database.connections.mysql.url')) {
            throw new \RuntimeException('MySQL測試未隔離');
        }

        return $app;
    }
}
