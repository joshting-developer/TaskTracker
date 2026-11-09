<?php

// 獨立volume持有隨機秘密；root秘密不掛載到API／Queue／Reverb。
foreach (['/run/tasktracker-root', '/run/tasktracker-app'] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $handle = fopen($directory.'/.initialize.lock', 'c');
    if (! $handle || ! flock($handle, LOCK_EX)) {
        throw new RuntimeException('無法取得MySQL秘密初始化鎖');
    }
    $path = $directory.'/password';
    if (! is_file($path)) {
        $temporary = $path.'.'.bin2hex(random_bytes(8));
        file_put_contents($temporary, bin2hex(random_bytes(32)), LOCK_EX);
        chmod($temporary, 0444);
        rename($temporary, $path);
    }
    if (filesize($path) < 32) {
        throw new RuntimeException('MySQL秘密格式錯誤，拒絕重設既有密碼');
    }
    flock($handle, LOCK_UN);
    fclose($handle);
}
