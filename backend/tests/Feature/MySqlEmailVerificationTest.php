<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\EmailVerificationCases;
use Tests\MySqlTestCase;

/** 隔離MySQL驗證註冊啟用與持久化次數 */
class MySqlEmailVerificationTest extends MySqlTestCase
{
    use DatabaseTransactions, EmailVerificationCases;
}
