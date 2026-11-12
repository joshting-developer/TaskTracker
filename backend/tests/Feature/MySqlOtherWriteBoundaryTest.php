<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\OtherWriteBoundaryCases;
use Tests\MySqlTestCase;

/** 只有獨立MySQL測試Compose可執行，驗相同非Issue／Time契約。 */
class MySqlOtherWriteBoundaryTest extends MySqlTestCase
{
    use DatabaseTransactions, OtherWriteBoundaryCases;
}
