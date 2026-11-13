<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssueActivityCountCases;
use Tests\MySqlTestCase;

/** 活動聚合筆數與卡片工時權限HTTP回歸 */
class MySqlIssueActivityCountsTest extends MySqlTestCase
{
    use DatabaseTransactions, IssueActivityCountCases;
}
