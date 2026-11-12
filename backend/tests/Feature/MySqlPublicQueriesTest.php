<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PublicQueryCases;
use Tests\MySqlTestCase;

/** 公開只讀API的隔離HTTP與資料範圍驗證。 */
class MySqlPublicQueriesTest extends MySqlTestCase
{
    use DatabaseTransactions, PublicQueryCases;
}
