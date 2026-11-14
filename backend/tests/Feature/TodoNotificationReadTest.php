<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TodoNotificationReadCases;
use Tests\TestCase;

/** 隔離通知批次已讀驗證 */
class TodoNotificationReadTest extends TestCase
{
    use RefreshDatabase, TodoNotificationReadCases;
}
