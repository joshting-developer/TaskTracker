<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\EpicEstimateProjectionCases;
use Tests\MySqlTestCase;

/** 真MySQL驗證批次估時投影與限定參數 */
class MySqlEpicEstimateProjectionTest extends MySqlTestCase
{
    use DatabaseTransactions, EpicEstimateProjectionCases;
}
