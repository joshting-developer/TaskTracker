<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\PublicTodoApiCases;
use Tests\MySqlTestCase;

/** TT-311：獨立MySQL驗證公開API小待辦。 */
class MySqlPublicTodoApiTest extends MySqlTestCase
{
    use DatabaseTransactions, PublicTodoApiCases;
}
