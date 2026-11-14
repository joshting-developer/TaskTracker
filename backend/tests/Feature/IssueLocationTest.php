<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssueLocationCases;
use Tests\TestCase;

/** SQLite隔離卡片位置與卡號授權 */
class IssueLocationTest extends TestCase
{
    use IssueLocationCases, RefreshDatabase;
}
