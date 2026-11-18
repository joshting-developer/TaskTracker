<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublicTimeEntryCases;
use Tests\TestCase;

/** 公開工時MySQL契約 */
class PublicTimeEntriesTest extends TestCase
{
    use PublicTimeEntryCases, RefreshDatabase;
}
