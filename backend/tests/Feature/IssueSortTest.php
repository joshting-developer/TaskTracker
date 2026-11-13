<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssueSortCases;
use Tests\TestCase;

/** TT-306：SQLite memory驗證任務排序與方向。 */
class IssueSortTest extends TestCase
{
    use IssueSortCases, RefreshDatabase;
}
