<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BoardArchiveCases;
use Tests\MySqlTestCase;

/** TT-283：獨立MySQL驗證看板封存清單與還原。 */
class MySqlBoardArchiveTest extends MySqlTestCase
{
    use BoardArchiveCases, DatabaseTransactions;
}
