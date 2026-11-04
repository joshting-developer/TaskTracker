<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 對應workspace_members的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class WorkspaceMember extends Model
{
    protected $table = 'workspace_members';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * 取得workspace關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * 取得user關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 取得assignments關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return HasMany
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'member_id');
    }

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['legacy_attributes' => 'array'];
    }
}
