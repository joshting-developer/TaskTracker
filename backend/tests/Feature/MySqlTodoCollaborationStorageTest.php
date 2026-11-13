<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\TodoCollaborationStorageCases;
use Tests\MySqlTestCase;

/** 隔離MySQL協作資料範圍與外鍵 */
class MySqlTodoCollaborationStorageTest extends MySqlTestCase
{
    use DatabaseTransactions, TodoCollaborationStorageCases;
}
