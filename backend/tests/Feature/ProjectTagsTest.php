<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ProjectTagCases;
use Tests\TestCase;

/** 隔離資料庫驗證公開與 Session 功能契約 */
class ProjectTagsTest extends TestCase
{
    use ProjectTagCases, RefreshDatabase;
}
