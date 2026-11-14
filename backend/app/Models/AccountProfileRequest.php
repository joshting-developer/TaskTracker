<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 帳號層級的個人資料修改重試紀錄, 不包含憑證 */
class AccountProfileRequest extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * 保存最小修改結果
     *
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return ['result' => 'array'];
    }
}
