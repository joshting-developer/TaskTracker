<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\IssueLinkQueryCases;
use Tests\TestCase;

/** 隔離記憶體資料庫的關聯寫入回歸。 */
class IssueLinkQueryTest extends TestCase
{
    use IssueLinkQueryCases, RefreshDatabase;

    /**
     * 經真實HTTP執行同交易寫入。
     *
     * @param  string  $operation  已註冊的RPC操作
     * @param  array<string,mixed>  $input  測試輸入，可明確指定原requestId
     * @return TestResponse 真實HTTP測試回應
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }
}
