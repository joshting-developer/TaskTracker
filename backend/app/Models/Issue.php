<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 對應issues的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class Issue extends Model
{
    protected $table = 'issues';

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
     * 取得assignee關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'assignee_id');
    }

    /**
     * 取得parent關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'parent_issue_id');
    }

    /**
     * 取得children關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Issue::class, 'parent_issue_id');
    }

    /**
     * 取得epic關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'epic_id');
    }

    /**
     * 取得comments關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return HasMany
     */
    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class, 'issue_id');
    }

    /**
     * 取得timeEntries關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return HasMany
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'issue_id');
    }

    /**
     * 取得status關聯，使用資料庫內部外鍵避免不同租戶公開ID衝突。
     *
     * @return BelongsTo
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'state_id');
    }

    /**
     * 取得目前任務的關聯卡片；反向紀錄由領域服務同交易維護。
     *
     * @return BelongsToMany
     */
    public function relatedIssues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_relations', 'issue_id', 'related_issue_id')->withPivot(['workspace_id', 'project_id', 'position'])->orderByPivot('position');
    }

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['legacy_attributes' => 'array'];
    }
}
