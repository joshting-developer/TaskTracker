<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** TT-294：紙條私有圖片, 只屬於工作區與紙條, 不關聯Project */
class TodoAttachment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['storage_disk', 'storage_key', 'upload_signature', 'upload_request_id'];

    /** metadata的時間與數字型別
     * @return array 型別映射
     */
    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'expires_at' => 'immutable_datetime'];
    }
}
