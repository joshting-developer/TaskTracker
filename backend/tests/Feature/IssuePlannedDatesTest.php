<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssuePlannedDateCases;
use Tests\TestCase;

class IssuePlannedDatesTest extends TestCase
{
    use IssuePlannedDateCases, RefreshDatabase;
}
