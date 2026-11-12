<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WorkspaceSettingsReadCases;
use Tests\TestCase;

/** 工作區設定分責查詢的隔離HTTP測試。 */
class WorkspaceSettingsReadTest extends TestCase
{
    use RefreshDatabase, WorkspaceSettingsReadCases;

    /**
     * 經正式寫入入口建立測試狀態。
     *
     * @param  string  $operation  RPC名稱
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse HTTP回應
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }
}
