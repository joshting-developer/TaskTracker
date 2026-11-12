<?php

namespace App\Database;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** MySQL以UTC DATETIME(6)儲存，HTTP仍使用原本ISO 8601字串。 */
class DatabaseTimes
{
    /**
     * 把公開ISO時間轉成資料庫datetime(6)字串；空值保持null。
     *
     * @param  ?string  $value  ISO 8601時間或null
     * @return string|null 資料庫格式時間
     */
    public static function store(?string $value): ?string
    {
        return $value && DB::getDriverName() === 'mysql'
            ? CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d H:i:s.u') : $value;
    }

    public static function public(?string $value): ?string
    {
        return $value && DB::getDriverName() === 'mysql'
            ? CarbonImmutable::parse($value, 'UTC')->utc()->toISOString() : $value;
    }
}
