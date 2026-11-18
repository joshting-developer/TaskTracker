<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\HttpOperationLogCases;
use Tests\TestCase;

/** TT-260：隔離 MySQL隔離驗證HTTP操作紀錄。 */
class HttpOperationLogTest extends TestCase
{
    use HttpOperationLogCases, RefreshDatabase;
}
