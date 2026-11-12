<?php

use Tests\Support\DependencyInventory;

/** 純原始碼盤點，不bootstrap Laravel／讀環境秘密／接資料庫；輸出JSON至stdout。 */
require __DIR__.'/../tests/Support/DependencyInventory.php';
$root = dirname(__DIR__);
$scanner = new DependencyInventory;
$files = [];
$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$owned = new RecursiveCallbackFilterIterator($directory, function ($file) use ($root) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

    return ! ($file->isDir() && in_array($file->getFilename(), ['vendor', 'storage', 'node_modules', '.git'], true))
        && $relative !== 'bootstrap/cache';
});
foreach (new RecursiveIteratorIterator($owned) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $files[] = $scanner->file($path, file_get_contents($file->getPathname()));
}
$files[] = $scanner->file('artisan', file_get_contents($root.'/artisan'));
usort($files, fn ($a, $b) => strcmp($a['path'], $b['path']));
$counts = [];
foreach ($files as $file) {
    foreach ($file['sites'] as $site) {
        $counts[$site['kind']] = ($counts[$site['kind']] ?? 0) + 1;
    }
}
ksort($counts);
echo json_encode(['format' => 1, 'scope' => 'owned PHP only; no vendor/storage/bootstrap cache; lexical first-class constructor graph, not full type/call analysis', 'fileCount' => count($files), 'siteCounts' => $counts, 'constructorGraph' => $scanner->graph($files), 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
