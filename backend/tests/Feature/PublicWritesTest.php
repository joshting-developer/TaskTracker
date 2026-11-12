<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublicWriteCases;
use Tests\TestCase;

/** 公開寫入HTTP／實際DB相容性。 */
class PublicWritesTest extends TestCase
{
    use PublicWriteCases, RefreshDatabase;
}
