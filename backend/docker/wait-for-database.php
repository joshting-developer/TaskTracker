<?php

// Local Compose startup only; production releases keep their own migration procedure.
if ((getenv('DB_CONNECTION') ?: 'mysql') !== 'mysql') {
    return;
}
$deadline = microtime(true) + 120;
do {
    $file = getenv('DB_PASSWORD_FILE');
    $password = $file && is_file($file) ? trim(file_get_contents($file)) : (getenv('DB_PASSWORD') ?: '');
    try {
        $connection = new PDO('mysql:host='.(getenv('DB_HOST') ?: 'mysql').';port='.(getenv('DB_PORT') ?: '3306').';dbname='.(getenv('DB_DATABASE') ?: 'tasktracker'), getenv('DB_USERNAME') ?: 'tasktracker', $password, [PDO::ATTR_TIMEOUT => 3]);

        return;
    } catch (PDOException) {
        usleep(1000000);
    }
} while (microtime(true) < $deadline);
throw new RuntimeException('Local MySQL did not become ready; credentials withheld');
