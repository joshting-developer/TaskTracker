<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RecordContextCases;
use Tests\TestCase;

/** 隔離快速回歸；同一trait另由原生MySQL驗證。 */
class RecordContextTest extends TestCase
{
    use RecordContextCases, RefreshDatabase;

    /** 經HTTP建立明確scope的人造測試資料。
     * @param  string  $operation  API操作
     * @param  array  $input  輸入欄位
     * @return TestResponse
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }
}
