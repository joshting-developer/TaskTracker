<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AdminDashboardCases;
use Tests\TestCase;

/** 預設memory隔離的管理登入與統計 */
class AdminDashboardTest extends TestCase
{
    use AdminDashboardCases, RefreshDatabase;
}
