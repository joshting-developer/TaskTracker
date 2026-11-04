<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 對應projects的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class Project extends Model
{
    protected $table = 'projects';

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
     * 取得issues關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return HasMany
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'project_id');
    }

    /**
     * 取得statuses關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return HasMany
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(ProjectStatus::class, 'project_id');
    }

    /**
     * 取得boards關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return HasMany
     */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class, 'project_id');
    }

    /**
     * 取得members關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return HasMany
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }

    /**
     * 取得lead關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'lead_id');
    }

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['legacy_attributes' => 'array'];
    }
}
