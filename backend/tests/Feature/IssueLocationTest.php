<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** 舊版無Project深連結只解析可讀卡片的位置。 */
class IssueLocationTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * 只回傳id／projectId；無ACL、跨Workspace與不存在皆為找不到。
     *
     * @return void
     */
    public function test_legacy_issue_location_is_minimal_and_scoped(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'owner-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Locate'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Locate', 'code' => 'LOC'])->assertOk()->json('data');
        $private = $this->rpc('createProject', ['name' => 'Private', 'code' => 'PRIV'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Visible', 'description' => 'secret body'])->assertOk()->json('data');
        $hidden = $this->rpc('createIssue', ['projectId' => $private['id'], 'title' => 'Hidden'])->assertOk()->json('data');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $data = $this->getJson('/api/v1/issue-locations/'.$issue['id'])->assertOk()->json('data');
        $this->assertSame(['id' => $issue['id'], 'projectId' => $project['id']], $data);
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'description') || str_contains($q['query'], 'audit_events') || str_contains($q['query'], 'comments')));
        $this->getJson('/api/v1/issue-locations/'.Str::uuid())->assertStatus(404);
        $this->getJson('/api/v1/issue-locations/'.$issue['id'], ['X-Workspace-ID' => 'wrong'])->assertStatus(409);

        $reader = User::create(['id' => (string) Str::uuid(), 'name' => 'Reader', 'email' => 'reader-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        (new RecordStore)->forWorkspace($identity['workspace']['id'])->put('members', $reader->id, ['id' => $reader->id, 'name' => $reader->name, 'email' => $reader->email, 'role' => 'user']);
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'roleId' => 'reader', 'expectedRevision' => 0])->assertOk();
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $reader->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson('/api/v1/issue-locations/'.$issue['id'])->assertOk()->assertJsonPath('data.projectId', $project['id']);
        $this->getJson('/api/v1/issue-locations/'.$hidden['id'])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        Auth::logout();
        $this->postJson('/api/auth/register', ['name' => 'Stranger', 'email' => 'stranger-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Other'])->assertOk();
        $this->getJson('/api/v1/issue-locations/'.$issue['id'])->assertStatus(404);
    }
}
