<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 目前交接路徑, 退回會pop但不刪除經手歷史 */
class TodoAssignmentPath extends Model
{
    public $timestamps = false;

    protected $table = 'todo_assignment_paths';

    protected $guarded = ['*'];
}
