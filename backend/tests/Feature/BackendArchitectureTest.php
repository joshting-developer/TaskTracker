<?php

namespace Tests\Feature;

use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 驗證HTTP分層整理仍能讀取舊重試紀錄、維持JSON邊界與租戶資料契約。
 */
class BackendArchitectureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 建立此測試獨立使用的帳號與工作空間。
     *
     * @return array
     */
    private function registerActor(): array
    {
        return $this->postJson('/api/auth/register', [
            'name' => 'Architecture Reviewer',
            'email' => 'architecture@example.test',
            'password' => 'architecture-test-password',
            'workspaceName' => 'Architecture Workspace',
        ])->assertOk()->assertHeader('X-CSRF-TOKEN')->json('data');
    }

    /**
     * 舊版已保存的簽章仍可重播，不能重新寫入或因版本過期失敗。
     *
     * @return void
     */
    #[Test]
    public function replay_success_read_existing_signature(): void
    {
        $actor = $this->registerActor();
        $project = $this->postJson('/api/rpc/createProject', [
            'name' => 'Before', 'code' => 'REPLAY', 'requestId' => 'create-project',
        ])->assertOk()->json('data');
        $saved = [...$project, 'name' => 'Old replay', 'revision' => 2];
        $store = (new RecordStore)->forWorkspace($actor['workspace']['id']);
        $store->put('projects', $project['id'], $saved);
        // 這是舊演算法輸出的固定JSON順序，不呼叫新canonical自我驗證。
        $canonical = '["updateProject",{"expectedRevision":1,"id":"'.$project['id'].'","name":"Old replay","requestId":"old-update"}]';
        $key = $actor['user']['id'].':old-update';
        $store->put('requests', $key, [
            'id' => $key, 'signature' => hash('sha256', $canonical), 'result' => $saved,
        ]);
        $response = $this->postJson('/api/rpc/updateProject', [
            'requestId' => 'old-update', 'name' => 'Old replay',
            'id' => $project['id'], 'expectedRevision' => 1,
        ])->assertOk();
        $this->assertSame(['ok', 'data'], array_keys($response->json()));
        $this->assertSame($saved, $response->json('data'));
        $this->assertSame($saved, $store->require('projects', $project['id']));
        $this->postJson('/api/rpc/updateProject', [
            'requestId' => 'old-update', 'name' => 'Different',
            'id' => $project['id'], 'expectedRevision' => 1,
        ])->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_CONFLICT');
    }

    /**
     * FormRequest不改寫既有錯誤碼，錯誤請求後仍可繼續正常讀取。
     *
     * @return void
     */
    #[Test]
    public function payload_failure_reject_non_object_and_invalid_json(): void
    {
        $this->registerActor();
        foreach (['[]', 'null', '"text"', '{invalid', json_encode(['large' => str_repeat('x', 50000)])] as $body) {
            $this->call('POST', '/api/rpc/getWorkspace', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ], $body)->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        }
        $this->post('/api/rpc/getWorkspace', [])->assertStatus(400)->assertJsonPath('error.message', '需要 JSON 資料');
        $this->postJson('/api/rpc/getWorkspace', ['requestId' => 'read'])->assertOk()->assertJsonCount(1, 'data.members');
    }

    /**
     * 未知操作保留登入檢查，登出回應也不被Resource增加第二層data。
     *
     * @return void
     */
    #[Test]
    public function routing_success_preserve_unknown_and_logout_contract(): void
    {
        $this->registerActor();
        foreach (['canonical', 'createSavedView', 'unknown'] as $operation) {
            $this->postJson('/api/rpc/'.$operation, ['requestId' => 'unknown'])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        }
        $this->post('/api/auth/logout')->assertOk()->assertHeader('X-CSRF-TOKEN')->assertExactJson(['ok' => true, 'data' => null]);
        $this->postJson('/api/rpc/unknown', ['requestId' => 'unauthenticated'])->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * Eloquent資料入口保留舊payload型別、順序及同ID的Workspace隔離。
     *
     * @return void
     */
    #[Test]
    public function record_store_success_preserve_payload_and_namespace(): void
    {
        $first = (new RecordStore)->forWorkspace('first-fixture');
        $second = (new RecordStore)->forWorkspace('second-fixture');
        $payload = ['id' => 'same', 'title' => '中文', 'nullable' => null, 'list' => [], 'flag' => false, 'minutes' => 30, 'fraction' => 1.5];
        $first->put('issues', 'same', $payload);
        $first->put('issues', 'next', ['id' => 'next']);
        $second->put('issues', 'same', ['id' => 'same', 'title' => '另一空間']);
        $this->assertSame($payload, $first->require('issues', 'same'));
        $this->assertSame('另一空間', $second->require('issues', 'same')['title']);
        $this->assertSame(['same', 'next'], array_column($first->all('issues'), 'id'));
        $first->put('issues', 'same', [...$payload, 'revision' => 2]);
        $this->assertSame(['same', 'next'], array_column($first->all('issues'), 'id'));
        $this->assertDatabaseCount('records', 3);
    }
}
