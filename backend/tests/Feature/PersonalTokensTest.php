<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PersonalTokenCases;
use Tests\TestCase;

/** 隔離快速PAT契約測試。 */
class PersonalTokensTest extends TestCase
{
    use PersonalTokenCases, RefreshDatabase;
}
