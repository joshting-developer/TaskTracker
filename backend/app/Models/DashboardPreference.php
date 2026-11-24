<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** TT-186：工作區成員的首頁小工具順序與顯示 */
class DashboardPreference extends Model
{
    protected $guarded = ['id'];

    /** JSON與數字型別
     * @return array 型別映射
     */
    protected function casts(): array
    {
        return ['widgets' => 'array', 'revision' => 'integer'];
    }
}
