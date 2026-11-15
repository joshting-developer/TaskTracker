<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WorkspaceMemberStatusCases;
use Tests\MySqlTestCase;

/** InnoDB資格生命週期及回滾 */
class MySqlWorkspaceMemberStatusTest extends MySqlTestCase
{
    use DatabaseTransactions, WorkspaceMemberStatusCases;
}
