<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublicExternalRefCases;
use Tests\TestCase;

/** TT-171：外部識別upsert／查詢（memory DB）。 */
class PublicExternalRefsTest extends TestCase
{
    use PublicExternalRefCases, RefreshDatabase;
}
