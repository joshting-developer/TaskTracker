<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\RegistersVerifiedFixtures;

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
     * 只接受獨立Compose的tasktracker_test帳號與允許的schema，強制改用隔離MySQL連線後建立應用程式。
     *
     * @return Application 測試用應用程式
     */
    public function createApplication()
    {
        if (! in_array($this->testDatabase, ['tasktracker_test', 'tasktracker_test_import'], true)) {
            throw new \RuntimeException('不允許的測試資料庫');
        }
        if (getenv('TASKTRACKER_MYSQL_TEST') !== '1' || getenv('DB_USERNAME') !== 'tasktracker_test') {
            throw new \RuntimeException('不是隔離MySQL帳號');
        }
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => 'mysql', 'DB_DATABASE' => $this->testDatabase, 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_DELIVERY_MODE' => 'queue', 'BROADCAST_CONNECTION' => 'null'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = parent::createApplication();
        if ($app['db']->connection()->getDatabaseName() !== $this->testDatabase || $app['db']->connection()->getDriverName() !== 'mysql') {
            throw new \RuntimeException('MySQL測試未隔離');
        }

        return $app;
    }
}
