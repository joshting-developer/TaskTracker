<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\OtherWriteBoundaryCases;
use Tests\TestCase;

/** memory隔離驗證非Issue／Time傳輸及結果契約。 */
class OtherWriteBoundaryTest extends TestCase
{
    use OtherWriteBoundaryCases, RefreshDatabase;
}
