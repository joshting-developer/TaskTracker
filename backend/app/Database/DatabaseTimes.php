<?php

namespace App\Database;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** MySQL以UTC DATETIME(6)儲存，HTTP仍使用原本ISO 8601字串。 */
class DatabaseTimes
{
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
