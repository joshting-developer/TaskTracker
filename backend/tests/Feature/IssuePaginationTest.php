<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssuePaginationCases;
use Tests\TestCase;

/** 任務列表分頁界限HTTP回歸 */
class IssuePaginationTest extends TestCase
{
    use IssuePaginationCases, RefreshDatabase;
}
