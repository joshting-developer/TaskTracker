<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\TokenAuthorizationCases;
use Tests\MySqlTestCase;

/** 純Bearer來源及撤權的隔離資料庫驗證。 */
class MySqlTokenAuthorizationTest extends MySqlTestCase
{
    use DatabaseTransactions, TokenAuthorizationCases;
}
