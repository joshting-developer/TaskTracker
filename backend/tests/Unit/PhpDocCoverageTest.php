<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\PhpDocInventory;

/** TT-235：自有後端必須齊備的範圍維持PHPDoc零缺漏；只讀原始碼，不bootstrap Laravel。 */
class PhpDocCoverageTest extends TestCase
{
    /**
     * 掃描器能分辨缺漏、參數不符、缺@return與正常docblock。
     *
     * @return void
     */
    public function test_inventory_detects_missing_and_mismatched_docblocks(): void
    {
        $source = <<<'PHP'
        <?php
        /** 範例類別。 */
        class Sample
        {
            /**
             * 有完整說明。
             *
             * @param  string  $name  名稱
             * @return string 結果
             */
            public function ok(string $name): string { return $name; }

            public function missing(): void {}

            /** @return void */
            public function noSummary(): void {}

            /**
             * 少了參數。
             */
            public function params(int $id): void {}
        }
        PHP;
        $kinds = array_column((new PhpDocInventory)->file('Sample.php', $source)['issues'], 'kind', 'symbol');
        $this->assertSame(['missing' => 'method-missing', 'noSummary' => 'summary-missing', 'params(id)' => 'param-undocumented', 'params' => 'return-missing'], $kinds);
    }

    /**
     * app、seeders、全部測試、migration及框架設定目錄沒有PHPDoc缺漏。
     * 排除bootstrap/cache產物；tools與database/factories另由CLI在完整來源掛載下掃描。
     *
     * @return void
     */
    public function test_required_backend_scope_has_no_phpdoc_gaps(): void
    {
        $root = dirname(__DIR__, 2);
        $scanner = new PhpDocInventory;
        $issues = [];
        foreach (['app', 'database/seeders', 'tests', 'database/migrations', 'routes', 'config', 'bootstrap'] as $directory) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() === 'php' && ! str_starts_with($file->getPathname(), $root.'/bootstrap/cache/')) {
                    $path = substr($file->getPathname(), strlen($root) + 1);
                    foreach ($scanner->file($path, file_get_contents($file->getPathname()))['issues'] as $issue) {
                        $issues[] = "{$path}:{$issue['line']} {$issue['kind']} {$issue['symbol']}";
                    }
                }
            }
        }
        $this->assertSame([], $issues);
    }
}
