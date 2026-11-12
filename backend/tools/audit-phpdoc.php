<?php

use Tests\Support\PhpDocInventory;

/** 純原始碼盤點自有後端PHPDoc，不bootstrap Laravel；輸出JSON摘要，加--list列出每項問題。 */
require __DIR__.'/../tests/Support/PhpDocInventory.php';
$root = dirname(__DIR__);
$scanner = new PhpDocInventory;
// required保留原統計分組；reference自TT-261同樣須零缺漏，Feature／Unit／migration由PhpDocCoverageTest擋回歸。
$required = ['app/', 'database/seeders/', 'database/factories/', 'tools/', 'tests/Support/', 'tests/Concerns/', 'tests/TestCase.php', 'tests/MySqlTestCase.php'];
$reference = ['tests/Feature/', 'tests/Unit/', 'database/migrations/', 'routes/', 'config/', 'bootstrap/'];
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    fn ($file) => ! ($file->isDir() && in_array($file->getFilename(), ['vendor', 'storage', 'node_modules', '.git', 'cache'], true))));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $group = null;
    foreach ($required as $prefix) {
        if (str_starts_with($path, $prefix)) {
            $group = 'required';
        }
    }
    foreach ($reference as $prefix) {
        if ($group === null && str_starts_with($path, $prefix)) {
            $group = 'reference';
        }
    }
    if ($group === null) {
        continue;
    }
    $files[] = ['group' => $group, ...$scanner->file($path, file_get_contents($file->getPathname()))];
}
usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));
$summary = [];
foreach ($files as $file) {
    $s = &$summary[$file['group']];
    $s['files'] = ($s['files'] ?? 0) + 1;
    $s['classes'] = ($s['classes'] ?? 0) + $file['classes'];
    $s['methods'] = ($s['methods'] ?? 0) + $file['methods'];
    $s['filesWithIssues'] = ($s['filesWithIssues'] ?? 0) + ($file['issues'] ? 1 : 0);
    foreach ($file['issues'] as $issue) {
        $s['issues'][$issue['kind']] = ($s['issues'][$issue['kind']] ?? 0) + 1;
    }
    unset($s);
}
$output = ['summary' => $summary];
if (in_array('--list', $argv, true)) {
    $output['files'] = array_values(array_filter($files, fn ($file) => $file['issues'] && (in_array('--all', $argv, true) || $file['group'] === 'required')));
}
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
