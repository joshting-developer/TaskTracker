<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TodoCollaborationCases;
use Tests\TestCase;

/** memory隔離待辦三人協作 */
class TodoCollaborationTest extends TestCase
{
    use RefreshDatabase, TodoCollaborationCases;
}
