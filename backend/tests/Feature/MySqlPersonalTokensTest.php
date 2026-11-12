<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PersonalTokenCases;
use Tests\MySqlTestCase;

/** 原生MySQL PAT索引／日期／權限與HTTP契約，資料rollback。 */
class MySqlPersonalTokensTest extends MySqlTestCase
{
    use DatabaseTransactions, PersonalTokenCases;
}
