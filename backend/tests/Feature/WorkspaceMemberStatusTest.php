<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WorkspaceMemberStatusCases;
use Tests\TestCase;

/** 隔離 MySQL資格生命週期 */
class WorkspaceMemberStatusTest extends TestCase
{
    use RefreshDatabase, WorkspaceMemberStatusCases;
}
