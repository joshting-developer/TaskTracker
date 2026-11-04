<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 映射既有records資料表，固定主鍵及欄位，不新增業務schema。
 */
class StoredRecord extends Model
{
    protected $table = 'records';

    protected $primaryKey = 'sequence';

    public $timestamps = false;

    protected $fillable = ['kind', 'id', 'payload'];
}
