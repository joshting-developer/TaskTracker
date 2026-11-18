<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../../tools/isolated-test-connection.php';

/** 驗證測試工具不能因切換共用MySQL而放寬正式庫、跨Agent或帳號限制 */
class SharedTestConnectionGuardTest extends TestCase
{
    /**
     * 合法與拒絕案例都不會建立資料庫連線
     *
     * @return void
     */
    public function test_requires_testing_flag_owner_database_and_derived_account(): void
    {
        $original = [];
        foreach (['APP_ENV' => 'testing', 'TASKTRACKER_MYSQL_TEST' => '1', 'TASKTRACKER_TEST_AGENT' => 'codex', 'TASKTRACKER_STANDALONE_TEST' => '0'] as $key => $value) {
            $original[$key] = getenv($key);
            putenv($key.'='.$value);
        }
        try {
            $database = 'sp_tasktracker_codex_test';
            $username = 'sp_'.substr(hash('sha256', $database), 0, 24);
            $this->assertTrue(isIsolatedTestConnection($database, $username, 'side-project-mysql'));
            foreach ([['task_tracker', $username, 'side-project-mysql'], ['tasktracker_test', 'tasktracker_test', 'mysql'], [$database, 'root', 'side-project-mysql'], [$database, $username, 'production.example.test'], ['sp_tasktracker_claude_test', 'sp_'.substr(hash('sha256', 'sp_tasktracker_claude_test'), 0, 24), 'side-project-mysql'], ['sp_tasktracker_codex_dev', $username, 'side-project-mysql']] as [$name, $user, $host]) {
                $this->assertFalse(isIsolatedTestConnection($name, $user, $host));
            }
            putenv('TASKTRACKER_STANDALONE_TEST=1');
            $this->assertTrue(isIsolatedTestConnection('tasktracker_test', 'tasktracker_test', 'mysql'));
            $this->assertTrue(isIsolatedTestConnection('tasktracker_test_import', 'tasktracker_test', 'mysql'));
            $this->assertFalse(isIsolatedTestConnection('tasktracker', 'tasktracker_test', 'mysql'));
            $this->assertFalse(isIsolatedTestConnection('tasktracker_test', 'root', 'mysql'));
            putenv('APP_ENV=production');
            $this->assertFalse(isIsolatedTestConnection($database, $username, 'side-project-mysql'));
            putenv('APP_ENV=testing');
            putenv('TASKTRACKER_MYSQL_TEST=0');
            $this->assertFalse(isIsolatedTestConnection($database, $username, 'side-project-mysql'));
        } finally {
            foreach ($original as $key => $value) {
                putenv($value === false ? $key : $key.'='.$value);
            }
        }
    }

    /**
     * 統一測試基底在啟動框架與重設資料前拒絕不安全的連線設定
     *
     * @return void
     */
    public function test_application_bootstrap_rejects_non_test_connections(): void
    {
        $script = 'require "vendor/autoload.php"; '
            .'$test = new class("probe") extends \Tests\TestCase {}; '
            .'try { $test->createApplication(); exit(1); } '
            .'catch (\RuntimeException $error) { echo $error->getMessage(); }';
        foreach ([
            ['TASKTRACKER_MYSQL_TEST' => '0'],
            ['TASKTRACKER_TEST_DATABASE' => 'tasktracker'],
            ['TASKTRACKER_TEST_USERNAME' => 'root'],
            ['TASKTRACKER_TEST_AGENT' => 'claude', 'TASKTRACKER_TEST_DATABASE' => 'sp_tasktracker_codex_test'],
        ] as $override) {
            $process = new Process([PHP_BINARY, '-r', $script], dirname(__DIR__, 2), [
                'APP_ENV' => 'testing', 'TASKTRACKER_MYSQL_TEST' => '1',
                'TASKTRACKER_STANDALONE_TEST' => '0', 'TASKTRACKER_TEST_AGENT' => 'codex',
                'TASKTRACKER_TEST_DATABASE' => 'sp_tasktracker_codex_test',
                'TASKTRACKER_TEST_USERNAME' => 'sp_'.substr(hash('sha256', 'sp_tasktracker_codex_test'), 0, 24),
                ...$override,
            ]);
            $process->setTimeout(10)->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('隔離', $process->getOutput());
        }
    }
}
