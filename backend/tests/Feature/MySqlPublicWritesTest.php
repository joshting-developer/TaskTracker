<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PublicWriteCases;
use Tests\MySqlTestCase;

/** 公開寫入HTTP／實際DB相容性。 */
class MySqlPublicWritesTest extends MySqlTestCase
{
    use DatabaseTransactions, PublicWriteCases;
}
