<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssueSortCases;
use Tests\MySqlTestCase;

/** TT-306：獨立MySQL驗證任務排序與方向。 */
class MySqlIssueSortTest extends MySqlTestCase
{
    use DatabaseTransactions, IssueSortCases;
}
