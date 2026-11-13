<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BoardArchiveCases;
use Tests\TestCase;

/** TT-283：SQLite memory驗證看板封存清單與還原。 */
class BoardArchiveTest extends TestCase
{
    use BoardArchiveCases, RefreshDatabase;
}
