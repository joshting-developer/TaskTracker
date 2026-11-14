<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WorkspaceCreationCases;
use Tests\MySqlTestCase;

/** InnoDB下的建立鎖序與receipt保存 */
class MySqlWorkspaceCreationTest extends MySqlTestCase
{
    use DatabaseTransactions, WorkspaceCreationCases;
}
