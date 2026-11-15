<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorkspaceMemberStatusCases;
use Tests\TestCase;

/** 隔離SQLite資格生命週期 */
class WorkspaceMemberStatusTest extends TestCase
{
    use RefreshDatabase, WorkspaceMemberStatusCases;
}
