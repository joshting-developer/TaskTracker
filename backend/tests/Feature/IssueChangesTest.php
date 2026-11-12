<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\IssueChangesCases;
use Tests\TestCase;

/** 隔離memory增量HTTP契約。 */
class IssueChangesTest extends TestCase
{
    use IssueChangesCases, RefreshDatabase;
}
