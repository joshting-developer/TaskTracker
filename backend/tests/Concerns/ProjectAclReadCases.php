<?php

namespace Tests\Concerns;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\ProjectAccessControl;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 兩種隔離資料庫共用的ACL資料範圍及批次讀取驗證。 */
trait ProjectAclReadCases
{
    /**
     * 驗證metadata／成員／候選／歷史分責及無逐成員SQL成長。
     *
     * @return void
     */
    public function test_project_acl_reads_are_bounded_batched_and_permission_scoped(): void
    {
        $email = 'acl-read-'.Str::uuid().'@example.test';
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => 'ACL'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'ACL', 'code' => 'ACL'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Private', 'code' => 'PRIVATE'])->assertOk()->json('data');
        $model = Project::where('public_id', $project['id'])->firstOrFail();
        $password = Hash::make('test-local-password');
        $members = [];
        for ($index = 0; $index < 105; $index++) {
            $user = User::create(['id' => (string) Str::uuid(), 'name' => sprintf('Person %03d', $index), 'email' => Str::uuid().'@example.test', 'password' => $password]);
            $member = WorkspaceMember::create(['workspace_id' => $model->workspace_id, 'user_id' => $user->id, 'role' => 'user']);
            $members[] = $user;
            if ($index < 25) {
                ProjectMember::create(['public_id' => $project['id'].':'.$user->id, 'workspace_id' => $model->workspace_id, 'project_id' => $model->id, 'member_id' => $member->id, 'role_id' => $index === 0 ? 'maintainer' : 'developer']);
            }
        }
        $path = '/api/v1/projects/'.$project['id'].'/acl';
        DB::enableQueryLog();
        DB::flushQueryLog();
        $metadata = $this->getJson($path)->assertOk()->assertJsonPath('data.revision', 0)->json('data');
        $this->assertCount(4, $metadata['roles']);
        $this->assertArrayNotHasKey('members', $metadata);
        $this->assertArrayNotHasKey('history', $metadata);
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'audit_events')));
        DB::flushQueryLog();
        $first = $this->getJson($path.'/members?perPage=20')->assertOk()->assertJsonPath('data.total', 26)->assertJsonCount(20, 'data.items')->json('data.items');
        $smallQueries = count(DB::getQueryLog());
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'audit_events')));
        DB::flushQueryLog();
        $all = $this->getJson($path.'/members?perPage=100')->assertOk()->assertJsonCount(26, 'data.items')->json('data.items');
        $this->assertSame($smallQueries, count(DB::getQueryLog()), '成員增加不應逐筆重查資格／歷史');
        $inherited = collect($all)->firstWhere('userId', $owner['user']['id']);
        $this->assertSame('workspace', $inherited['source']);
        $this->assertFalse($inherited['canChange']);
        $second = $this->getJson($path.'/members?page=2')->assertOk()->assertJsonCount(6, 'data.items')->json('data.items');
        $this->assertSame([], array_intersect(array_column($first, 'userId'), array_column($second, 'userId')));
        $this->getJson($path.'/members?search=Person%20024')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson($path.'/candidates?perPage=100')->assertOk()->assertJsonCount(100, 'data.items')->assertJsonPath('data.total', 106);
        $this->getJson($path.'/candidates?page=2&perPage=100')->assertOk()->assertJsonCount(6, 'data.items');
        $this->getJson($path.'/candidates?search=Person%20104')->assertOk()->assertJsonPath('data.items.0.id', $members[104]->id);
        $this->getJson($path.'/members?perPage=101')->assertStatus(400);
        $this->getJson($path, ['X-Workspace-ID' => 'other'])->assertStatus(409);
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $members[24]->id, 'roleId' => 'reader', 'expectedRevision' => 0])->assertOk();
        $acl = ProjectAccessControl::where('project_id', $model->id)->firstOrFail();
        $source = AuditEvent::where('entity_type', 'projectAcls')->where('entity_id', $acl->id)->firstOrFail();
        for ($index = 1; $index < 25; $index++) {
            $copy = $source->replicate();
            $copy->position = $index;
            $copy->save();
        }
        $this->getJson($path.'/history')->assertOk()->assertJsonPath('data.total', 25)->assertJsonCount(20, 'data.items')->assertJsonPath('data.items.0.targetName', 'Person 024');
        $this->getJson($path.'/history?page=2')->assertOk()->assertJsonCount(5, 'data.items');
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $members[0]->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson($path)->assertOk();
        $this->getJson('/api/v1/projects/'.$other['id'].'/acl')->assertStatus(404);
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $members[24]->email, 'password' => 'test-local-password'])->assertOk();
        foreach (['', '/members', '/candidates', '/history'] as $suffix) {
            $this->getJson($path.$suffix)->assertStatus(403);
        }
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'test-local-password'])->assertOk();
        $this->rpc('removeProjectMember', ['projectId' => $project['id'], 'userId' => $members[0]->id, 'expectedRevision' => 1])->assertOk();
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $members[0]->email, 'password' => 'test-local-password'])->assertOk();
        foreach (['', '/members', '/candidates', '/history'] as $suffix) {
            $this->getJson($path.$suffix)->assertStatus(404);
        }
    }
}
