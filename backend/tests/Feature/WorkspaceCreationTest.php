<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorkspaceCreationCases;
use Tests\TestCase;

/** memory隔離工作空間建立用例 */
class WorkspaceCreationTest extends TestCase
{
    use RefreshDatabase, WorkspaceCreationCases;
}
