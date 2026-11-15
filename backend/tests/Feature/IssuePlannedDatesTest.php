<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssuePlannedDateCases;
use Tests\TestCase;

/** 隔離資料庫驗證公開與 Session 功能契約 */
class IssuePlannedDatesTest extends TestCase
{
    use IssuePlannedDateCases, RefreshDatabase;
}
