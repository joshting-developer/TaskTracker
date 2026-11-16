<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorkspaceTimeZoneCases;
use Tests\TestCase;

/** 隔離工作區顯示時區、權限與歷史保存驗證 */
class WorkspaceTimeZoneTest extends TestCase
{
    use RefreshDatabase, WorkspaceTimeZoneCases;
}
