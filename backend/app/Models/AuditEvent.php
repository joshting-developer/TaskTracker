<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 對應audit_events的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class AuditEvent extends Model
{
    protected $table = 'audit_events';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['event_data' => 'array'];
    }
}
