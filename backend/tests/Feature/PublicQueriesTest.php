<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublicQueryCases;
use Tests\TestCase;

/** 公開只讀API的隔離HTTP與資料範圍驗證。 */
class PublicQueriesTest extends TestCase
{
    use PublicQueryCases, RefreshDatabase;
}
