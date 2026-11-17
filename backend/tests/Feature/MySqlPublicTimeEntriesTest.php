<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PublicTimeEntryCases;
use Tests\MySqlTestCase;

/** 公開工時MySQL契約 */
class MySqlPublicTimeEntriesTest extends MySqlTestCase
{
    use DatabaseTransactions, PublicTimeEntryCases;
}
