<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** TT-171外部識別對照；只由Repository在已授權Project範圍內讀寫。 */
class IssueExternalRef extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
