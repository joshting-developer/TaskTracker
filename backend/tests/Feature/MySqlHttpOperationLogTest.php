<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\HttpOperationLogCases;
use Tests\MySqlTestCase;

/** TT-260：只在獨立MySQL測試Compose執行，驗DATETIME(6)、索引分頁與失敗隔離。 */
class MySqlHttpOperationLogTest extends MySqlTestCase
{
    use DatabaseTransactions, HttpOperationLogCases;
}
