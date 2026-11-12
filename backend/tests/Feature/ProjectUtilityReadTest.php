<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ProjectUtilityReadCases;
use Tests\TestCase;

/** 專案用量與通知查詢的隔離HTTP驗證。 */
class ProjectUtilityReadTest extends TestCase
{
    use ProjectUtilityReadCases, RefreshDatabase;

    /**
     * 透過正式HTTP寫入建立人造測試資料。
     *
     * @param  string  $operation  RPC名稱
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse HTTP測試回應
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }
}
