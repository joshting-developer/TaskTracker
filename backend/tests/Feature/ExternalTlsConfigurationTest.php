<?php

namespace Tests\Feature;

use Tests\TestCase;

/** 只驗設定輸入, 不連現用外部服務或接觸憑證 */
class ExternalTlsConfigurationTest extends TestCase
{
    /** TLS不能停用peer或名稱驗證, default／cache共用有界timeout
     * @return void
     */
    public function test_redis_tls_configuration_verifies_server_identity_and_bounds_timeouts(): void
    {
        $values = ['REDIS_SCHEME' => 'tls', 'REDIS_HOST' => 'redis.example.test', 'REDIS_SSL_CA' => '/synthetic/ca.pem', 'REDIS_CONNECT_TIMEOUT' => '2', 'REDIS_READ_TIMEOUT' => '4'];
        $previous = [];
        foreach ($values as $key => $value) {
            $previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        try {
            $config = require base_path('config/database.php');
            foreach (['default', 'cache'] as $name) {
                $connection = $config['redis'][$name];
                $this->assertSame('tls', $connection['scheme']);
                $this->assertSame('/synthetic/ca.pem', $connection['ssl']['cafile']);
                $this->assertTrue($connection['ssl']['verify_peer']);
                $this->assertTrue($connection['ssl']['verify_peer_name']);
                $this->assertSame('redis.example.test', $connection['ssl']['peer_name']);
                $this->assertSame(2.0, $connection['timeout']);
                $this->assertSame(4.0, $connection['read_write_timeout']);
            }
        } finally {
            foreach ($previous as $key => [$env, $server, $process]) {
                if ($env === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $env;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
                putenv($process === false ? $key : $key.'='.$process);
            }
        }
    }
}
