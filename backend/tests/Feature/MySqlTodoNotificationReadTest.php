<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\TodoNotificationReadCases;
use Tests\MySqlTestCase;

/** 隔離通知批次已讀驗證 */
class MySqlTodoNotificationReadTest extends MySqlTestCase
{
    use DatabaseTransactions, TodoNotificationReadCases;
}
