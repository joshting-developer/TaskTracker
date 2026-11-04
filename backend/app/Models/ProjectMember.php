<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 對應project_members的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class ProjectMember extends Model
{
    protected $table = 'project_members';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * 取得project關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * 取得member關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'member_id');
    }

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['legacy_attributes' => 'array'];
    }
}
