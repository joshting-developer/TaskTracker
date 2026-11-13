<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublicTodoApiCases;
use Tests\TestCase;

/** TT-311：SQLite memory驗證公開API小待辦。 */
class PublicTodoApiTest extends TestCase
{
    use PublicTodoApiCases, RefreshDatabase;
}
