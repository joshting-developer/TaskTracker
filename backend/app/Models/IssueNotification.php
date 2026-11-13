<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 對應issue_notifications的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class IssueNotification extends Model
{
    protected $table = 'issue_notifications';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** 回傳JSON快照及歷史相容欄位的型別。
     * @return array
     */
    protected function casts(): array
    {
        return ['legacy_attributes' => 'array'];
    }

    /**
     * 取得同租戶外鍵所指向的卡片，查詢層限定預載欄位。
     *
     * @return BelongsTo 卡片關聯
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'issue_id');
    }

    /**
     * 取得通知所屬專案, 公開「我的通知」用來重驗收件人目前還能不能讀這個專案。
     *
     * @return BelongsTo 專案關聯
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * 取得有效收件資格查詢所需的Workspace成員。
     *
     * @return BelongsTo 收件人關聯
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'recipient_id');
    }
}
