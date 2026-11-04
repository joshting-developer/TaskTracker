<?php

namespace Tests\Feature;

use App\Authorization\WorkspacePermissions;
use App\Models\User;
use App\Repositories\RecordStore;
use App\Services\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_repeatable_scoped_and_creates_real_relations_and_time(): void
    {
        $store = new RecordStore;
        $store->put('workspaces', 'personal', ['id' => 'personal', 'name' => '保留']);
        $store->forWorkspace('personal')->put('projects', 'personal-project', ['id' => 'personal-project', 'name' => '不可刪除', 'code' => 'PERSONAL']);
        $seed = app(DemoData::class);
        $seed->seed(password: 'seed-test-password');
        $tenant = $store->forWorkspace(DemoData::WORKSPACE_ID);
        $this->assertSame(['first', 'second'], array_column($tenant->all('projects'), 'name'));
        $this->assertCount(0, $tenant->all('savedViews'));
        $this->assertCount(14, $tenant->all('issues'));
        $this->assertSame([], array_diff(array_column($tenant->all('issues'), 'type'), ['epic', 'task', 'bug']));
        $this->assertCount(6, $tenant->all('timeEntries'));
        $this->assertSame(4, count(array_filter($tenant->all('issues'), fn ($issue) => $issue['parentIssueId'] !== '')));
        $this->assertSame(4, count(array_filter($tenant->all('issues'), fn ($issue) => $issue['relatedIssueIds'] !== [])));
        $this->assertTrue(Hash::check('seed-test-password', User::find(DemoData::USER_ID)->password));
        $before = DB::table('issues')->count();
        $this->assertFalse($seed->seed()['created']);
        $this->assertSame($before, DB::table('issues')->count());
        $project = $tenant->all('projects')[0];
        $tenant->put('projects', $project['id'], [...$project, 'name' => '自行修改']);
        $seed->seed();
        $this->assertSame('自行修改', $tenant->require('projects', $project['id'])['name']);
        $seed->seed(reset: true, password: 'seed-test-password');
        $this->assertSame(['first', 'second'], array_column($tenant->all('projects'), 'name'));
        $this->assertCount(14, $tenant->all('issues'));
        $this->assertSame('不可刪除', $store->forWorkspace('personal')->require('projects', 'personal-project')['name']);
        $this->postJson('/api/auth/login', ['email' => DemoData::EMAIL, 'password' => 'seed-test-password'])->assertOk()->assertJsonPath('data.workspace.name', 'f-ws');
    }

    public function test_seed_upgrade_adds_accounts_without_changing_legacy_data_and_rejects_collisions(): void
    {
        $store = new RecordStore;
        $store->put('workspaces', DemoData::WORKSPACE_ID, ['id' => DemoData::WORKSPACE_ID, 'name' => 'f-ws']);
        $store->put('demoSeeds', DemoData::WORKSPACE_ID, ['id' => DemoData::WORKSPACE_ID, 'version' => 1]);
        $tenant = $store->forWorkspace(DemoData::WORKSPACE_ID);
        $jack = User::create(['id' => '00000000-0000-4000-8000-000000000101', 'name' => 'Jack', 'email' => 'jack100398@gmail.com', 'password' => Hash::make('legacy-test-password')]);
        $tenant->put('members', $jack->id, ['id' => $jack->id, 'name' => 'Jack', 'role' => 'owner']);
        $tenant->put('projects', 'original', ['id' => 'original', 'name' => '手改保留', 'code' => 'ORIGINAL', 'revision' => 7]);
        $tenant->put('issues', 'original-issue', ['id' => 'original-issue', 'projectId' => 'original', 'identifier' => 'ORIGINAL-1', 'state' => 'todo', 'title' => '保留卡片', 'assignee' => $jack->id, 'history' => [['actor' => 'Jack']]]);
        $tenant->put('timeEntries', 'original-time', ['id' => 'original-time', 'projectId' => 'original', 'issueId' => 'original-issue', 'userId' => $jack->id, 'workDate' => '2026-10-01', 'minutes' => 60]);
        $snapshot = [$tenant->all('projects'), $tenant->all('issues'), $tenant->all('timeEntries')];
        // 最後一組帳號衝突時, 不可留下前三組或更新 marker
        $collision = User::create(['id' => (string) Str::uuid(), 'name' => '已存在', 'email' => 'developer@example.test', 'password' => Hash::make('other-test-password')]);
        try { app(DemoData::class)->seed(password: 'seed-test-password'); $this->fail('衝突必須拒絕'); }
        catch (\RuntimeException $failure) { $this->assertStringContainsString('不覆蓋', $failure->getMessage()); }
        $this->assertSame(2, User::count());
        $this->assertSame(1, $store->require('demoSeeds', DemoData::WORKSPACE_ID)['version']);
        $collision->delete();
        $this->assertTrue(app(DemoData::class)->seed(password: 'seed-test-password')['upgraded']);
        $this->assertCount(5, $tenant->all('members'));
        $this->assertSame($snapshot, [$tenant->all('projects'), $tenant->all('issues'), $tenant->all('timeEntries')]);
        $this->assertTrue(Hash::check('legacy-test-password', $jack->fresh()->password));
        $hash = User::find(DemoData::USER_ID)->password;
        $this->assertFalse(app(DemoData::class)->seed()['upgraded']);
        $this->assertSame($hash, User::find(DemoData::USER_ID)->password);
    }

    public function test_four_accounts_have_enforced_permissions_and_own_time_restrictions(): void
    {
        app(DemoData::class)->seed(password: 'seed-test-password');
        $tenant = (new RecordStore)->forWorkspace(DemoData::WORKSPACE_ID);
        $this->assertCount(4, $tenant->all('members'));
        $rpc = fn ($operation, $input = []) => $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
        foreach (DemoData::ACCOUNTS as $role => $account) {
            Auth::logout();
            $login = $this->postJson('/api/auth/login', ['email' => $account['email'], 'password' => 'seed-test-password'])->assertOk()->assertJsonPath('data.user.name', $role)->assertJsonPath('data.role', in_array($role, ['maintainer', 'developer']) ? 'user' : $role)->json('data');
            $this->assertSame(WorkspacePermissions::resolve($login['role']), $login['permissions']);
            $data = $rpc('getWorkspace')->assertOk()->json('data');
            $this->assertContains('first', array_column($data['projects'], 'name'));
            if ($role === 'developer') $this->assertNotContains('second', array_column($data['projects'], 'name'));
            else $this->assertContains('second', array_column($data['projects'], 'name'));
            foreach ($data['notifications'] as $notification) if (!in_array('notification.readTeamAny', $login['permissions'], true) && $role === 'developer') $this->assertSame($account['id'], $notification['recipientId']);
            $project = $data['projects'][0];
            $canManage = $role !== 'developer';
            $rpc('createProject', ['name' => '帳號測試', 'code' => strtoupper(substr($role, 0, 4))])->assertStatus(in_array($role, ['owner', 'manager']) ? 200 : 403);
            $rpc('updateProject', ['id' => $project['id'], 'expectedRevision' => $project['revision'], 'description' => $role])->assertStatus($canManage ? 200 : 403);
            $project = $tenant->require('projects', $project['id']);
            $rpc('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => $project['revision'], 'statuses' => $project['statuses']])->assertStatus($canManage ? 200 : 403);
            $rpc('createBoard', ['projectId' => $project['id'], 'name' => $role, 'selectedIssueIds' => []])->assertStatus($canManage ? 200 : 403);
            $rpc('inviteMember', ['email' => $role.'-invite@example.test'])->assertStatus(in_array($role, ['owner', 'manager']) ? 200 : 403);
            $rpc('smtpStatus')->assertStatus($role === 'owner' ? 200 : 403);
            $issue = $rpc('createIssue', ['projectId' => $project['id'], 'title' => $role.' 的任務'])->assertOk()->json('data');
            $rpc('addComment', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'content' => '可以留言'])->assertOk();
            $entry = $rpc('logTime', ['issueId' => $issue['id'], 'workDate' => '2026-10-01', 'minutes' => 15, 'note' => $role])->assertOk()->json('data');
            $this->assertSame($account['id'], $entry['userId']);
            $rpc('updateTime', ['id' => $entry['id'], 'expectedRevision' => $entry['revision'], 'issueId' => $issue['id'], 'workDate' => '2026-10-01', 'minutes' => 20])->assertOk();
            $other = array_values(array_filter($tenant->all('timeEntries'), fn ($item) => $item['userId'] !== $account['id']))[0];
            $rpc('updateTime', ['id' => $other['id'], 'expectedRevision' => $other['revision'], 'issueId' => $other['issueId'], 'workDate' => $other['workDate'], 'minutes' => 20])->assertStatus(403);
        }
        // 顯示名稱或前端自稱 owner 都不能提高實際 permissions
        $developer = DemoData::ACCOUNTS['developer'];
        User::find($developer['id'])->update(['name' => 'owner']);
        $rpc('createProject', ['name' => '假冒', 'code' => 'FAKE', 'role' => 'owner', 'permissions' => ['project.create']])->assertStatus(403);
    }

    public function test_seed_refuses_production_and_test_database_is_always_memory(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        app()->instance('env', 'production');
        $this->artisan('tasktracker:seed-demo')->assertFailed();
        $this->assertSame(0, User::count());
    }
}
