<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorkspaceMemberCapacityCases;
use Tests\TestCase;

/** 隔離 MySQL的成員人數契約 */
class WorkspaceMemberCapacityTest extends TestCase
{
    use RefreshDatabase, WorkspaceMemberCapacityCases;
}
