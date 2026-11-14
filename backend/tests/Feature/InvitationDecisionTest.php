<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InvitationDecisionCases;
use Tests\TestCase;

/** SQLite隔離驗證邀請二階段決定 */
class InvitationDecisionTest extends TestCase
{
    use InvitationDecisionCases, RefreshDatabase;
}
