<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssueDeletionCases;
use Tests\TestCase;

/** TT-250：隔離 MySQL驗證永久刪卡。 */
class IssueDeletionTest extends TestCase
{
    use IssueDeletionCases, RefreshDatabase;
}
