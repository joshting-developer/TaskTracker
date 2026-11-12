<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * 對應http_operation_logs的觀測紀錄；保存期限到期由model:prune刪除。
 */
class HttpOperationLog extends Model
{
    use Prunable;

    /** 保存天數；超過即可由排程清除。 */
    public const RETENTION_DAYS = 90;

    protected $table = 'http_operation_logs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['occurred_at' => 'immutable_datetime', 'status' => 'integer', 'duration_ms' => 'integer'];

    /**
     * 超過保存期限的紀錄，供Laravel model:prune批次刪除。
     *
     * @return Builder<HttpOperationLog> 待清除的紀錄查詢
     */
    public function prunable(): Builder
    {
        return static::where('occurred_at', '<', now()->utc()->subDays(self::RETENTION_DAYS));
    }
}
