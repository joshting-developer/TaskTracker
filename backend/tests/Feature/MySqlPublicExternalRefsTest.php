<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PublicExternalRefCases;
use Tests\MySqlTestCase;

/** TT-171：外部識別在真MySQL上的唯一索引、utf8mb4_bin大小寫與交易行為。 */
class MySqlPublicExternalRefsTest extends MySqlTestCase
{
    use DatabaseTransactions, PublicExternalRefCases;
}
