<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssueActivityCountCases;
use Tests\TestCase;

/** 活動聚合筆數與卡片工時權限HTTP回歸 */
class IssueActivityCountsTest extends TestCase
{
    use IssueActivityCountCases, RefreshDatabase;
}
