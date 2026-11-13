<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\InlineMailCases;
use Tests\MySqlTestCase;

/** MySQL commit與同步寄信邊界 */
class MySqlInlineMailTest extends MySqlTestCase
{
    use DatabaseTransactions, InlineMailCases;
}
