<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WorkspaceTimeZoneCases;
use Tests\MySqlTestCase;

/** 隔離工作區顯示時區、權限與歷史保存驗證 */
class MySqlWorkspaceTimeZoneTest extends MySqlTestCase
{
    use DatabaseTransactions, WorkspaceTimeZoneCases;
}
