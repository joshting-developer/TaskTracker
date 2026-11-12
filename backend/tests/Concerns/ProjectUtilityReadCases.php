<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 以相同資料範圍斷言驗證memory與獨立MySQL的專案管理查詢。 */
trait ProjectUtilityReadCases
{
    /**
     * 狀態用量只在SQL聚合目前Project，不讀卡片描述或活動；無狀態設定資格者拒絕。
     *
     * @return void
     */
    public function test_status_usage_is_project_scoped_aggregate(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Usage', 'email' => 'usage-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Usage'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Usage', 'code' => 'USAGE'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk()->json('data');
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Open', 'description' => str_repeat('large description ', 300)])->assertOk();
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Working', 'state' => 'inprogress'])->assertOk();
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Working 2', 'state' => 'inprogress'])->assertOk();
        $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Other', 'state' => 'closed'])->assertOk();
        $path = '/api/v1/projects/'.$project['id'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $usage = $this->getJson($path.'/status-usage')->assertOk()->json('data.usage');
        $this->assertSame(['inprogress' => 2, 'open' => 1], collect($usage)->sortKeys()->all(), '只計目前Project，未使用狀態不列');
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'audit_events') || str_contains($q['query'], 'description')));
        $this->getJson($path.'/status-usage', ['X-Workspace-ID' => 'wrong'])->assertStatus(409);
        $reader = User::create(['id' => (string) Str::uuid(), 'name' => 'Reader', 'email' => 'reader-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        (new RecordStore)->forWorkspace($identity['workspace']['id'])->put('members', $reader->id, ['id' => $reader->id, 'name' => $reader->name, 'email' => $reader->email, 'role' => 'user']);
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'roleId' => 'reader', 'expectedRevision' => 0])->assertOk();
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $reader->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson($path.'/status-usage')->assertStatus(403);
        $this->getJson('/api/v1/projects/'.$other['id'].'/status-usage')->assertStatus(404);
    }
}
