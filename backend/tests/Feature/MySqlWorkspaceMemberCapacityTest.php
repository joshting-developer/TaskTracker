<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WorkspaceMemberCapacityCases;
use Tests\MySqlTestCase;

/** 真實MySQL的成員人數契約 */
class MySqlWorkspaceMemberCapacityTest extends MySqlTestCase
{
    use DatabaseTransactions, WorkspaceMemberCapacityCases;
}
