<?php

// 只在空 volume 建立秘密, 各服務讀同一份環境, 不輸出金鑰
$directory = getenv('TASKTRACKER_ENV_PATH') ?: '/data';
$handle = fopen($directory.'/.initialize.lock', 'c');
if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('無法取得初始化鎖');
$file = $directory.'/.env';
if (!is_file($file)) {
    $temporary = $file.'.'.bin2hex(random_bytes(8));
    file_put_contents($temporary, 'APP_KEY=base64:'.base64_encode(random_bytes(32))."\nREVERB_APP_ID=tasktracker\nREVERB_APP_KEY=".bin2hex(random_bytes(16))."\nREVERB_APP_SECRET=".bin2hex(random_bytes(32))."\n", LOCK_EX);
    chmod($temporary, 0600);
    rename($temporary, $file);
}
$database = getenv('DB_DATABASE') ?: '/data/tasktracker.sqlite';
if (!is_file($database)) touch($database);
flock($handle, LOCK_UN);
fclose($handle);
