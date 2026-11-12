<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssueDeletionCases;
use Tests\MySqlTestCase;

/** TT-250：獨立MySQL驗證永久刪卡。 */
class MySqlIssueDeletionTest extends MySqlTestCase
{
    use DatabaseTransactions, IssueDeletionCases;
}
