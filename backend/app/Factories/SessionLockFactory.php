<?php

namespace App\Factories;

use App\Domain\DomainError;
use App\Http\Session\SessionLease;
use Illuminate\Database\DatabaseManager;

/** 在現用MySQL連線取得完整Session生命週期的命名鎖；不建立額外資料庫連線。 */
class SessionLockFactory
{
    /**
     * 注入穩定資料庫管理器，Session ID只在acquire傳入。
     *
     * @param  DatabaseManager  $database  取得Session所在的資料庫連線
     */
    public function __construct(private DatabaseManager $database) {}

    /**
     * 目前Session資料庫是否支援命名鎖（MySQL才支援）。
     *
     * @return bool 正式MySQL用連線級鎖；SQLite僅沿用隔離快測的框架鎖。
     */
    public function supported(): bool
    {
        return $this->database->connection()->getDriverName() === 'mysql';
    }

    /**
     * 等候最多指定秒數；持有期間無TTL，程序或DB連線關閉即由MySQL釋放。
     *
     * @param  string  $sessionName  Cookie名稱
     * @param  string  $sessionId  框架選定Session ID
     * @param  int  $waitSeconds  等待上限，非持有租期
     * @return SessionLease 當次請求必須finally釋放的鎖handle
     */
    public function acquire(string $sessionName, string $sessionId, int $waitSeconds): SessionLease
    {
        $connection = $this->database->connection();
        $scope = config('app.name').':'.config('app.env').':'.$connection->getDatabaseName().':'.$sessionName.':'.$sessionId;
        $name = 'tt-session:'.substr(hash('sha256', $scope), 0, 52);
        $result = $connection->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$name, $waitSeconds], false);
        if ((int) ($result->acquired ?? 0) !== 1) {
            DomainError::fail('CONFLICT', '其他分頁仍在處理登入操作，請稍後重試');
        }

        return new SessionLease($connection, $name);
    }
}
