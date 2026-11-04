<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 對應board_issues的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class BoardIssue extends Model
{
    protected $table = 'board_issues';

    public $timestamps = false;

    protected $guarded = ['id'];
}
