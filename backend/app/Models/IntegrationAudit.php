<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 追加式成功操作來源紀錄；不保存請求內容、原文或Token雜湊。 */
class IntegrationAudit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * 將能力及時間轉為值，避免輸出原始JSON字串。
     *
     * @return array<string,string> 稽核欄位的儲存轉型
     */
    protected function casts(): array
    {
        return ['abilities' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
