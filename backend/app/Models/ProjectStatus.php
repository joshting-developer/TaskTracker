<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 對應project_statuses的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class ProjectStatus extends Model
{
    protected $table = 'project_statuses';

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
}
