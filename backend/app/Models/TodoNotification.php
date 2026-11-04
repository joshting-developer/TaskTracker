<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 對應todo_notifications的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class TodoNotification extends Model
{
    protected $table = 'todo_notifications';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * 取得todo關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class, 'todo_id');
    }

    /**
     * 取得recipient關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'recipient_id');
    }

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['legacy_attributes' => 'array'];
    }
}
