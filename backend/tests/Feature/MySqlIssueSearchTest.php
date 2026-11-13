<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssueSearchCases;
use Tests\MySqlTestCase;

/** 搜尋契約真MySQL回歸 */
class MySqlIssueSearchTest extends MySqlTestCase
{
    use DatabaseTransactions, IssueSearchCases;
}
