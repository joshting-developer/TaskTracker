<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\InvitationDecisionCases;
use Tests\MySqlTestCase;

/** 真實InnoDB交易內驗證邀請決定 */
class MySqlInvitationDecisionTest extends MySqlTestCase
{
    use DatabaseTransactions, InvitationDecisionCases;
}
