<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 對應issue_comments的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class IssueComment extends Model
{
    protected $table = 'issue_comments';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * 取得issue關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'issue_id');
    }
}
