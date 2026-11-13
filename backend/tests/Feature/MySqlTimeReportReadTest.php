<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\TimeReportReadCases;
use Tests\MySqlTestCase;

/** 隔離MySQL驗證工時範圍、分頁與投影 */
class MySqlTimeReportReadTest extends MySqlTestCase
{
    use DatabaseTransactions, TimeReportReadCases;
}
