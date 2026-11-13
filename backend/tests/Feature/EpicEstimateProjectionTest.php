<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EpicEstimateProjectionCases;
use Tests\TestCase;

/** 快速隔離驗證當頁Epic估時與讀取範圍 */
class EpicEstimateProjectionTest extends TestCase
{
    use EpicEstimateProjectionCases, RefreshDatabase;
}
