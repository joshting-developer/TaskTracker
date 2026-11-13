<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 對應todos的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class Todo extends Model
{
    protected $table = 'todos';

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * 取得assignee關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'assignee_id');
    }

    /**
     * 取得creator關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'created_by_id');
    }

    /**
     * 只預載初始承辦者的User ID供交辦清單條件呈現。
     *
     * @return BelongsTo 初始承辦者資格
     */
    public function initialAssignee(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'initial_assignee_id');
    }

    /**
     * 取得convertedIssue關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function convertedIssue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'converted_issue_id');
    }

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['legacy_attributes' => 'array'];
    }

    /** 目前交接路徑僅用於有界EXISTS能力投影
     * @return HasMany 路徑資料
     */
    public function assignmentPaths(): HasMany
    {
        return $this->hasMany(TodoAssignmentPath::class, 'todo_id');
    }
}
