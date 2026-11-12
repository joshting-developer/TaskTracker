<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorkspaceContextCases;
use Tests\TestCase;

/** 隔離memory驗證可信Context與Factory重用邊界。 */
class WorkspaceContextTest extends TestCase
{
    use RefreshDatabase, WorkspaceContextCases;
}
