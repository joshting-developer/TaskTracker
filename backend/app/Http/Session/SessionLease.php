<?php

namespace App\Http\Session;

use App\Domain\DomainError;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Log;

/** 單次HTTP持有的MySQL連線級Session鎖；不設TTL、不跨請求共用。 */
final class SessionLease
{
    /**
     * 保存本請求持有的Session命名鎖。
     *
     * @param  Connection  $connection  實際取得GET_LOCK的連線
     * @param  string  $name  不含明文Session ID的鎖名稱
     */
    public function __construct(private Connection $connection, private string $name) {}

    /**
     * 防止連線斷線重建後繼續提交或保存已過期的Session快照。
     *
     * @return void
     */
    public function assertHeld(): void
    {
        $row = $this->connection->selectOne('SELECT IS_USED_LOCK(?) = CONNECTION_ID() AS held', [$this->name], false);
        if ((int) ($row->held ?? 0) !== 1) {
            DomainError::fail('CONFLICT', '登入操作鎖已失效，請重新整理並確認結果後重試');
        }
    }

    /**
     * 僅釋放本連線取得的鎖，不能解除另一個請求持有的鎖。
     *
     * @return void
     */
    public function release(): void
    {
        try {
            $this->connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$this->name], false);
        } catch (\Throwable) {
            // 清除PDO以釋放連線級鎖，也避免finally錯誤蓋掉原始業務失敗。
            $this->connection->disconnect();
            Log::warning('Session鎖釋放失敗，已關閉資料庫連線');
        }
    }
}
