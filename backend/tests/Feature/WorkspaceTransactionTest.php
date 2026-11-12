<?php

namespace Tests\Feature;

use App\Database\WriteLocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** TT-249：交易模式由呼叫端明確宣告；寫入才取User→Workspace鎖，租戶標頭比對與舊整包讀取例外不變。 */
class WorkspaceTransactionTest extends TestCase
{
    use RefreshDatabase;

    /** 記錄寫入鎖呼叫順序。 */
    private \ArrayObject $locks;

    /**
     * 以記錄呼叫的WriteLocks取代正式實作，仍執行原本的鎖。
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $locks = $this->locks = new \ArrayObject;
        $this->app->bind(WriteLocks::class, fn () => new class($locks) extends WriteLocks
        {
            /**
             * 保留紀錄物件。
             *
             * @param  \ArrayObject  $calls  呼叫紀錄
             */
            public function __construct(private \ArrayObject $calls) {}

            /**
             * 記錄後取User鎖。
             *
             * @param  mixed  $userId  User ID
             * @return void
             */
            public function account(mixed $userId): void
            {
                $this->calls[] = 'account';
                parent::account($userId);
            }

            /**
             * 記錄後取Workspace鎖。
             *
             * @param  mixed  $workspaceId  Workspace公開ID
             * @return void
             */
            public function workspace(mixed $workspaceId): void
            {
                $this->calls[] = 'workspace';
                parent::workspace($workspaceId);
            }
        });
    }

    /**
     * 透過正式HTTP寫入建立人造測試資料。
     *
     * @param  string  $operation  RPC名稱
     * @param  array<string,mixed>  $input  測試輸入
     * @param  array<string,string>  $headers  額外標頭
     * @return TestResponse HTTP測試回應
     */
    private function rpc(string $operation, array $input = [], array $headers = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input], $headers);
    }

    /**
     * 讀取不取鎖、寫入依序取鎖；租戶標頭不符時拒絕，舊整包讀取不比對。
     *
     * @return void
     */
    public function test_modes_control_locks_and_tenant_header(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Mode', 'email' => 'mode-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Mode'])->assertOk()->json('data');
        $this->locks->exchangeArray([]);
        $project = $this->rpc('createProject', ['name' => 'Mode', 'code' => 'MOD'])->assertOk()->json('data');
        $this->assertSame(['account', 'workspace'], $this->locks->getArrayCopy(), '寫入先鎖User再鎖Workspace');

        $this->locks->exchangeArray([]);
        $this->getJson('/api/v1/projects/'.$project['id'])->assertOk();
        $this->rpc('getTodos')->assertOk();
        $this->assertSame([], $this->locks->getArrayCopy(), 'v1查詢與舊RPC讀取不取寫入鎖');

        $wrong = ['X-Workspace-ID' => 'other-workspace'];
        $this->getJson('/api/v1/projects/'.$project['id'], $wrong)->assertStatus(409)->assertJsonPath('error.code', 'WORKSPACE_CHANGED');
        $this->rpc('updateProject', ['id' => $project['id'], 'name' => 'X', 'expectedRevision' => $project['revision']], $wrong)->assertStatus(409)->assertJsonPath('error.code', 'WORKSPACE_CHANGED');
        $this->rpc('getWorkspace', [], $wrong)->assertOk()->assertJsonPath('data.meta.workspace.id', $identity['workspace']['id']);
        $this->getJson('/api/v1/projects/'.$project['id'], ['X-Workspace-ID' => $identity['workspace']['id']])->assertOk();
    }
}
