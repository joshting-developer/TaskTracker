<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TodoCollaborationStorageCases;
use Tests\TestCase;

/** 隔離memory協作資料範圍與外鍵 */
class TodoCollaborationStorageTest extends TestCase
{
    use RefreshDatabase, TodoCollaborationStorageCases;
}
