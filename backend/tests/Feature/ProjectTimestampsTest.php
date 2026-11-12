<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ProjectTimestampCases;
use Tests\TestCase;

/** TT-274：SQLite memory隔離驗證專案時間戳與最近更新排序。 */
class ProjectTimestampsTest extends TestCase
{
    use ProjectTimestampCases, RefreshDatabase;
}
