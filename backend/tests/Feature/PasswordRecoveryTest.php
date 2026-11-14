<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PasswordRecoveryCases;
use Tests\TestCase;

/** memory SQLite恢復流程 */
class PasswordRecoveryTest extends TestCase
{
    use PasswordRecoveryCases, RefreshDatabase;
}
