<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WorkspaceInvitationCases;
use Tests\MySqlTestCase;

/** 原生MySQL隔離邀請郵件與待接受查詢 */
class MySqlWorkspaceInvitationTest extends MySqlTestCase
{
    use DatabaseTransactions, WorkspaceInvitationCases;
}
