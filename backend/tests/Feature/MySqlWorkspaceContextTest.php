<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\WorkspaceContextCases;
use Tests\MySqlTestCase;

/** 原生MySQL驗證資格重查及跨Actor／Workspace連續用例，fixture自動rollback。 */
class MySqlWorkspaceContextTest extends MySqlTestCase
{
    use DatabaseTransactions, WorkspaceContextCases;
}
