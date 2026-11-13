<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssueChildrenEpicCases;
use Tests\TestCase;

/** memory隔離驗證子卡批次寫入規則 */
class IssueChildrenEpicTest extends TestCase
{
    use IssueChildrenEpicCases, RefreshDatabase;
}
