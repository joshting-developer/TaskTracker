<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 映射歷史records封存表，僅供升級、回退與舊版匯入。
 */
class StoredRecord extends Model
{
    protected $table = 'records';

    protected $primaryKey = 'sequence';

    public $timestamps = false;

    protected $fillable = ['kind', 'id', 'payload'];
}
