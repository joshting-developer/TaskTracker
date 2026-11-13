<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EmailVerificationCases;
use Tests\TestCase;

/** 隔離memory驗證註冊啟用規則 */
class EmailVerificationTest extends TestCase
{
    use EmailVerificationCases, RefreshDatabase;
}
