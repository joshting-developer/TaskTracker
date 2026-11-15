<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 永久保存工作區成員資格變更, 不作為授權快取 */
class WorkspaceMemberStatusEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
