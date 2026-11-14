<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** 每Email一筆重設雜湊, 不保存原文或自動序列化秘密 */
#[Guarded([])]
#[Hidden(['token'])]
class PasswordReset extends Model
{
    protected $table = 'password_reset_tokens';

    protected $primaryKey = 'email';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * 將重設申請時間轉為日期物件
     *
     * @return array<string,string> 申請時間型別
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
