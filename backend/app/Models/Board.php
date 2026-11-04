<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 對應boards的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class Board extends Model
{
    protected $table = 'boards';

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
     * 取得明確收錄的卡片；Epic動態展開由領域服務處理。
     *
     * @return BelongsToMany
     */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'board_issues')->withPivot(['workspace_id', 'project_id', 'position'])->orderByPivot('position');
    }

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['legacy_attributes' => 'array'];
    }
}
