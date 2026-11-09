<?php

namespace App\Database;

use Closure;
use Illuminate\Database\SQLiteConnection;

/** 保留Laravel交易生命週期，只讓明確唯讀用例延後取得SQLite鎖。 */
class WorkspaceSqliteConnection extends SQLiteConnection
{
    private bool $reading = false;

    /**
     * 在一致快照內讀取並由SQLite拒絕寫入，離開後還原連線狀態。
     *
     * @param  Closure  $callback  已確認不寫入的用例
     * @return mixed 用例結果
     */
    public function readTransaction(Closure $callback): mixed
    {
        $pdo = $this->getPdo();
        $previousQueryOnly = (int) $pdo->query('PRAGMA query_only')->fetchColumn();
        $previousReading = $this->reading;
        $pdo->exec('PRAGMA query_only = ON');
        $this->reading = true;
        try {
            return $this->transaction($callback);
        } finally {
            $this->reading = $previousReading;
            $pdo->exec('PRAGMA query_only = '.($previousQueryOnly ? 'ON' : 'OFF'));
        }
    }

    /** 唯讀用DEFERRED，其他交易仍沿用資料庫設定的IMMEDIATE。 */
    protected function executeBeginTransactionStatement()
    {
        if ($this->reading) {
            $this->getPdo()->exec('BEGIN DEFERRED TRANSACTION');
        } else {
            parent::executeBeginTransactionStatement();
        }
    }
}
