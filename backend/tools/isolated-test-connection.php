<?php

/**
 * 測試工具的共用防誤連線檢查, 不建立連線或修改資料
 *
 * @param  string|null  $database  啟動後可傳實際連線設定, 啟動前預設讀程序環境
 * @param  string|null  $username  實際資料庫帳號
 * @param  string|null  $host  實際資料庫主機
 * @return bool 是否為目前 Agent 的共用隔離測試帳號與資料庫
 */
function isIsolatedTestConnection(?string $database = null, ?string $username = null, ?string $host = null): bool
{
    $database ??= getenv('DB_DATABASE') ?: '';
    $username ??= getenv('DB_USERNAME') ?: '';
    $host ??= getenv('DB_HOST') ?: '';
    if (getenv('APP_ENV') === 'testing' && getenv('TASKTRACKER_MYSQL_TEST') === '1' && getenv('TASKTRACKER_STANDALONE_TEST') === '1' && $host === 'mysql' && $username === 'tasktracker_test' && in_array($database, ['tasktracker_test', 'tasktracker_test_import'], true)) {
        return true;
    }
    if (getenv('APP_ENV') !== 'testing' || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || $host !== 'side-project-mysql') {
        return false;
    }
    if (! preg_match('/\Asp_tasktracker_(codex|claude|visual|ci)_(test|import|policy|inline|pagination|seed|realtime|r2|runtime)\z/', $database, $matches)) {
        return false;
    }

    return $matches[1] === getenv('TASKTRACKER_TEST_AGENT')
        && $username === 'sp_'.substr(hash('sha256', $database), 0, 24);
}
