<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InlineMailCases;
use Tests\TestCase;

/** memory隔離同步寄信 */
class InlineMailTest extends TestCase
{
    use InlineMailCases, RefreshDatabase;
}
