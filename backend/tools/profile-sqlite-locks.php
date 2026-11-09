<?php

/**
 * 用全新暫存SQLite與兩條連線比較讀取交易對寫入的影響。
 * 使用：docker compose exec -T backend php < backend/tools/profile-sqlite-locks.php
 * 不bootstrap Laravel、不接受資料庫路徑，不接觸業務資料或Session。
 */
if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('只允許CLI量測');
}
$path = tempnam(sys_get_temp_dir(), 'tasktracker-lock-');
if ($path === false) {
    throw new RuntimeException('無法建立隔離暫存檔');
}
$reader = $writer = null;
try {
    $reader = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $writer = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $reader->exec('PRAGMA journal_mode=WAL');
    $reader->exec('PRAGMA busy_timeout=200');
    $writer->exec('PRAGMA busy_timeout=200');
    $reader->exec('CREATE TABLE probe (id INTEGER PRIMARY KEY, value INTEGER)');
    $reader->exec('INSERT INTO probe VALUES (1, 0)');
    $samples = [];
    foreach (['IMMEDIATE', 'DEFERRED'] as $mode) {
        for ($run = 1; $run <= 5; $run++) {
            $reader->exec('BEGIN '.$mode.' TRANSACTION');
            $reader->query('SELECT * FROM probe')->fetchAll();
            $start = hrtime(true);
            try {
                $writer->exec('UPDATE probe SET value=value+1 WHERE id=1');
                $status = 'committed';
            } catch (PDOException $error) {
                if ((int) ($error->errorInfo[1] ?? 0) !== 5) {
                    throw $error;
                }
                $status = 'locked';
            } finally {
                $elapsed = (hrtime(true) - $start) / 1e6;
                $reader->exec('ROLLBACK');
            }
            $samples[] = ['mode' => $mode, 'run' => $run, 'writerStatus' => $status, 'writerMs' => round($elapsed, 3)];
        }
    }
    echo json_encode(['php' => PHP_VERSION, 'sqlite' => $reader->getAttribute(PDO::ATTR_SERVER_VERSION), 'journal' => 'WAL', 'busyTimeoutMs' => 200, 'samples' => $samples], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $reader = $writer = null;
    foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
