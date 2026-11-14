<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorkspaceInvitationCases;
use Tests\TestCase;

/** SQLite隔離邀請郵件與待接受查詢 */
class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase, WorkspaceInvitationCases;
}
