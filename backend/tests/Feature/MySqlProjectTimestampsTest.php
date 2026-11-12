<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\ProjectTimestampCases;
use Tests\MySqlTestCase;

/** TT-274：只在獨立MySQL測試Compose執行，驗DATETIME(6)時間戳、排序與補值。 */
class MySqlProjectTimestampsTest extends MySqlTestCase
{
    use DatabaseTransactions, ProjectTimestampCases;
}
