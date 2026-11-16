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

if (getenv('TASKTRACKER_INITIALIZE_MAIL') === '1') {
    foreach (['/run/mail-private', '/run/mail-ca'] as $directory) {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    $certificate = '/run/mail-private/cert.pem';
    $key = '/run/mail-private/key.pem';
    if (is_file($certificate) !== is_file($key)) {
        throw new RuntimeException('Incomplete local SMTP certificate; preserve the existing files');
    }
    if (! is_file($certificate)) {
        exec('openssl req -x509 -newkey rsa:2048 -nodes -keyout '.escapeshellarg($key).' -out '.escapeshellarg($certificate).' -sha256 -days 3650 -subj /CN=mailpit -addext '.escapeshellarg('subjectAltName=DNS:mailpit').' -addext '.escapeshellarg('basicConstraints=critical,CA:TRUE').' 2>&1', $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('Local SMTP certificate initialization failed');
        }
        chmod($key, 0600);
    }
    copy($certificate, '/run/mail-ca/cert.pem');
    chmod('/run/mail-ca/cert.pem', 0444);
}
