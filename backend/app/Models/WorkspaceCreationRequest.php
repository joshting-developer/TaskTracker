<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 本人建立工作空間的請求簽章與結果指標, 不直接輸出到API */
class WorkspaceCreationRequest extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
