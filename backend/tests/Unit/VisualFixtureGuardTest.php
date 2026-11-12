<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** 驗證視覺fixture在載入Laravel與資料庫前拒絕非專用測試環境。 */
class VisualFixtureGuardTest extends TestCase
{
    /**
     * 用獨立程序確認環境不符時不會進入fixture寫入。
     *
     * @param  string  $environment  明確注入的應用環境
     * @param  string  $testFlag  專用MySQL測試旗標
     * @return void
     */
    #[DataProvider('rejectedEnvironments')]
    public function test_rejects_environment_before_bootstrap(string $environment, string $testFlag): void
    {
        $process = new Process([PHP_BINARY, base_path('tools/visual-fixture-clock.php')], base_path(), ['APP_ENV' => $environment, 'TASKTRACKER_MYSQL_TEST' => $testFlag]);
        $process->setInput('{}');
        $process->setTimeout(5);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Only isolated visual testing is allowed', $process->getErrorOutput().$process->getOutput());
    }

    /**
     * 提供非testing環境及缺少專用旗標的拒絕案例。
     *
     * @return array<string,array{string,string}> 環境與旗標組合
     */
    public static function rejectedEnvironments(): array
    {
        return ['production' => ['production', '1'], 'local' => ['local', '1'], 'not-isolated' => ['testing', '0']];
    }
}
