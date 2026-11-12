<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ProjectAclReadCases;
use Tests\TestCase;

/** Project ACL分責查詢的隔離HTTP驗證。 */
class ProjectAclReadTest extends TestCase
{
    use ProjectAclReadCases, RefreshDatabase;

    /**
     * 使用正式寫入入口建立人造資料。
     *
     * @param  string  $operation  RPC名稱
     * @param  array<string,mixed>  $input  測試資料
     * @return TestResponse HTTP回應
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }
}
