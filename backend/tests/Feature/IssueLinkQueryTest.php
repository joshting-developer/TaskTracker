<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\IssueLinkQueryCases;
use Tests\TestCase;

/** 隔離記憶體資料庫的關聯寫入回歸。 */
class IssueLinkQueryTest extends TestCase
{
    use IssueLinkQueryCases, RefreshDatabase;

    /** 經真實HTTP執行同交易寫入。 */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }
}
