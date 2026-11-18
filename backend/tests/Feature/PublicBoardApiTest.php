<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublicBoardApiCases;
use Tests\TestCase;

/** MySQL隔離公開看板查詢 */
class PublicBoardApiTest extends TestCase
{
    use PublicBoardApiCases, RefreshDatabase;
}
