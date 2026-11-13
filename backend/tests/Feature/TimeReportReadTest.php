<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TimeReportReadCases;
use Tests\TestCase;

/** 隔離memory驗證新工時讀取契約 */
class TimeReportReadTest extends TestCase
{
    use RefreshDatabase, TimeReportReadCases;
}
