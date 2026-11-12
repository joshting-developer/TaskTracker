<?php

namespace App\Http\Session;

use Closure;
use Illuminate\Http\Request;

/**
 * 包住一次HTTP操作，在執行前後確認本請求仍持有Session鎖租期，避免逾時後覆寫其他分頁的Session。
 */
class SessionLeaseGuard
{
    /**
     * 執行操作並在前後驗證Session租期。
     *
     * @template T
     *
     * @param  Request  $request  持有當次鎖handle的HTTP請求
     * @param  Closure(): T  $operation  授權、版本與業務操作
     * @return T 操作結果
     */
    public function around(Request $request, Closure $operation): mixed
    {
        $lease = $request->attributes->get(SessionLease::class);
        $lease?->assertHeld();
        $result = $operation();
        $lease?->assertHeld();

        return $result;
    }
}
