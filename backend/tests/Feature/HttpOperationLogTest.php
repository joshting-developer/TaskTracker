<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\HttpOperationLogCases;
use Tests\TestCase;

/** TT-260：SQLite memory隔離驗證HTTP操作紀錄。 */
class HttpOperationLogTest extends TestCase
{
    use HttpOperationLogCases, RefreshDatabase;
}
