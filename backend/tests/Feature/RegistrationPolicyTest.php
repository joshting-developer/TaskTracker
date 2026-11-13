<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistrationPolicyCases;
use Tests\TestCase;

/** memory隔離驗註冊開關與邀請 */
class RegistrationPolicyTest extends TestCase
{
    use RefreshDatabase, RegistrationPolicyCases;
}
