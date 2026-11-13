<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 附件私有位置與生命週期, 對外僅由Resource投影 */
class Attachment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['storage_disk', 'storage_key', 'upload_signature', 'upload_request_id'];

    /** 所屬工作區
     * @return BelongsTo 工作區關聯
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** 所屬專案
     * @return BelongsTo 專案關聯
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** 綁定任務, pending為null
     * @return BelongsTo 任務關聯
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /** 上傳者帳號
     * @return BelongsTo 帳號關聯
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** metadata的時間與數字型別
     * @return array 型別映射
     */
    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'revision' => 'integer', 'expires_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime'];
    }
}
