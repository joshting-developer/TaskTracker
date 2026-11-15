<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssuePlannedDateCases;
use Tests\MySqlTestCase;

/** 隔離資料庫驗證公開與 Session 功能契約 */
class MySqlIssuePlannedDatesTest extends MySqlTestCase
{
    use DatabaseTransactions, IssuePlannedDateCases;
}
