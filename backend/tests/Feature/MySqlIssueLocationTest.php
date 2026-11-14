<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssueLocationCases;
use Tests\MySqlTestCase;

/** MySQL隔離卡片位置與卡號授權 */
class MySqlIssueLocationTest extends MySqlTestCase
{
    use DatabaseTransactions, IssueLocationCases;
}
