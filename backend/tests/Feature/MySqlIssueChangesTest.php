<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssueChangesCases;
use Tests\MySqlTestCase;

/** 真MySQL keyset／時間精度及權限契約。 */
class MySqlIssueChangesTest extends MySqlTestCase
{
    use DatabaseTransactions, IssueChangesCases;
}
