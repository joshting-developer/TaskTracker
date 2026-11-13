<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 帳號當次信箱驗證, 只保存驗證碼及連結的雜湊 */
class EmailVerification extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'token_hash'];

    /** 設定驗證次數與期限型別
     * @return array<string,string> 驗證期限與次數型別 */
    protected function casts(): array
    {
        return ['attempts' => 'integer', 'expires_at' => 'datetime', 'sent_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
