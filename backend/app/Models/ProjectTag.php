<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 專案標籤定義, 刪除時保留原定義供歷史查證 */
class ProjectTag extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
