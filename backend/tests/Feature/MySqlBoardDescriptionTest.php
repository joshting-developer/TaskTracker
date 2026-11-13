<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BoardDescriptionCases;
use Tests\MySqlTestCase;

/** TT-309：獨立MySQL驗證看板簡述。 */
class MySqlBoardDescriptionTest extends MySqlTestCase
{
    use BoardDescriptionCases, DatabaseTransactions;
}
