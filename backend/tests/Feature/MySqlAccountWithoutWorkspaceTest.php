<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\AccountWithoutWorkspaceCases;
use Tests\MySqlTestCase;

/** InnoDB的零工作區身分與設定重播 */
class MySqlAccountWithoutWorkspaceTest extends MySqlTestCase
{
    use AccountWithoutWorkspaceCases, DatabaseTransactions;
}
