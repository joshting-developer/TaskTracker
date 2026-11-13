<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\RegistersVerifiedFixtures;

/**
 * 預設測試基底：強制memory SQLite與隔離設定，避免測試碰到本機業務資料庫。
 */
abstract class TestCase extends BaseTestCase
{
    use RegistersVerifiedFixtures;

    /**
     * 建立強制使用memory SQLite的測試應用程式。
     *
     * @return Application 測試用應用程式
     */
    public function createApplication()
    {
        // Compose 的真實程序環境優先於 PHPUnit env；在 framework bootstrap 前強制隔離
        foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BROADCAST_CONNECTION' => 'null'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.url', null);
        $app['db']->purge('sqlite');
        $app['config']->set('broadcasting.default', 'null');
        if (! $app->environment('testing') || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('測試未隔離, 拒絕執行資料庫重設');
        }

        return $app;
    }
}
