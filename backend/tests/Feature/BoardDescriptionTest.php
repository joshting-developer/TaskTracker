<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BoardDescriptionCases;
use Tests\TestCase;

/** TT-309：隔離 MySQL驗證看板簡述。 */
class BoardDescriptionTest extends TestCase
{
    use BoardDescriptionCases, RefreshDatabase;
}
