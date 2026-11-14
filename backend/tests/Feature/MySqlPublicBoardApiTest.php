<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PublicBoardApiCases;
use Tests\MySqlTestCase;

/** 隔離MySQL公開看板查詢 */
class MySqlPublicBoardApiTest extends MySqlTestCase
{
    use DatabaseTransactions, PublicBoardApiCases;
}
