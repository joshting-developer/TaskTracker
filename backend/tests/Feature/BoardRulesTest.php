<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BoardRuleCases;
use Tests\TestCase;

/** SQLite 隔離看板條件與例外收錄 */
class BoardRulesTest extends TestCase
{
    use BoardRuleCases, RefreshDatabase;
}
