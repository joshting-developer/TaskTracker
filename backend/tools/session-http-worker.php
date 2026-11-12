<?php

use GuzzleHttp\Client;

/** 單次測試HTTP請求；憑證只從0600測試暫存檔讀取，不輸出Cookie。 */
require __DIR__.'/../vendor/autoload.php';
if (getenv('TASKTRACKER_MYSQL_TEST') !== '1') {
    throw new RuntimeException('不是隔離Session競態環境');
}
$fixture = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('#^http://127\.0\.0\.1:[0-9]+/api/test/session-slow$#', $fixture['url'])) {
    throw new RuntimeException('不允許的測試URL');
}
$client = new Client(['timeout' => 35, 'http_errors' => false]);
$response = $client->post($fixture['url'], ['headers' => $fixture['headers'], 'json' => $fixture['body']]);
file_put_contents($fixture['result'], json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getBody(), true)], JSON_THROW_ON_ERROR));
