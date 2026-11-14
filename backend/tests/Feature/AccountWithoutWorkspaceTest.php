<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AccountWithoutWorkspaceCases;
use Tests\TestCase;

/** memory隔離的零工作區帳號流程 */
class AccountWithoutWorkspaceTest extends TestCase
{
    use AccountWithoutWorkspaceCases, RefreshDatabase;
}
