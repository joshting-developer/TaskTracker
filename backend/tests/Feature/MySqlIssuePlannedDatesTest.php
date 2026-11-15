<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\IssuePlannedDateCases;
use Tests\MySqlTestCase;

class MySqlIssuePlannedDatesTest extends MySqlTestCase
{
    use DatabaseTransactions, IssuePlannedDateCases;
}
