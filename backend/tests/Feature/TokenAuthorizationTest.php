<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TokenAuthorizationCases;
use Tests\TestCase;

/** 純Bearer來源及撤權的隔離資料庫驗證。 */
class TokenAuthorizationTest extends TestCase
{
    use RefreshDatabase, TokenAuthorizationCases;
}
