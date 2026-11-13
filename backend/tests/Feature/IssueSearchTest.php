<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssueSearchCases;
use Tests\TestCase;

/** 搜尋契約memory隔離回歸 */
class IssueSearchTest extends TestCase
{
    use IssueSearchCases, RefreshDatabase;
}
