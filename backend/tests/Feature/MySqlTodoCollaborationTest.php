<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\TodoCollaborationCases;
use Tests\MySqlTestCase;

/** 原生MySQL待辦三人協作與原子回覆 */
class MySqlTodoCollaborationTest extends MySqlTestCase
{
    use DatabaseTransactions, TodoCollaborationCases;
}
