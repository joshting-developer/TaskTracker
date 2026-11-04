<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 對應issue_relations的關聯式資料模型；租戶範圍由repository及資料庫外鍵共同限制。
 */
class IssueRelation extends Model
{
    protected $table = 'issue_relations';

    public $timestamps = false;

    protected $guarded = ['id'];
}
