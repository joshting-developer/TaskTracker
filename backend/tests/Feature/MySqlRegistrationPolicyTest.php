<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\RegistrationPolicyCases;
use Tests\MySqlTestCase;

/** 原生隔離MySQL驗註冊開關與邀請 */
class MySqlRegistrationPolicyTest extends MySqlTestCase
{
    use DatabaseTransactions, RegistrationPolicyCases;
}
