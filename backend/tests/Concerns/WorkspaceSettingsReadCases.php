<?php

namespace Tests\Concerns;

use App\Events\ProjectChanged;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Repositories\ProjectReadRepository;
use App\Services\ProjectSignals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 同一資料範圍在memory及獨立MySQL驗證工作區設定讀寫。 */
trait WorkspaceSettingsReadCases
{
    /**
     * 一般設定不附目錄，成員SQL分頁，能力與寫入保留ownership及版本限制。
     *
     * @return void
     */
    public function test_workspace_settings_reads_are_split_bounded_and_permission_scoped(): void
    {
        $email = 'ws-read-'.Str::uuid().'@example.test';
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => 'Workspace Read'])->assertOk()->json('data');
        $workspace = Workspace::where('public_id', $owner['workspace']['id'])->firstOrFail();
        $password = Hash::make('test-local-password');
        $users = [];
        for ($index = 0; $index < 105; $index++) {
            $user = User::create(['id' => (string) Str::uuid(), 'name' => sprintf('Person %03d', $index), 'email' => Str::uuid().'@example.test', 'password' => $password]);
            WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $index === 0 ? 'manager' : 'user']);
            $users[] = $user;
        }
        $this->rpc('createProject', ['name' => 'Signal target', 'code' => 'SIGNAL'])->assertOk();
        Event::fake([ProjectChanged::class]);
        config(['broadcasting.default' => 'reverb']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        (new ProjectSignals($owner, app(ProjectReadRepository::class)))->workspaceChanged();
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'audit_events') || str_contains($q['query'], 'description') || str_contains($q['query'], 'project_statuses')));
        config(['broadcasting.default' => 'null']);
        DB::flushQueryLog();
        $general = $this->getJson('/api/v1/workspace/settings')->assertOk()->assertJsonPath('data.workspace.name', 'Workspace Read')->json('data');
        $this->assertSame(['workspace'], array_keys($general));
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'member_name') || str_contains($q['query'], 'audit_events') || str_contains($q['query'], 'project_members')));
        $this->postJson('/api/rpc/getWorkspaceSettings', ['requestId' => 'retired-settings-read'])->assertStatus(404);
        $this->getJson('/api/v1/workspace/member-roles')->assertOk()->assertJsonPath('data.roles.0.id', 'manager')->assertJsonPath('data.roles.1.id', 'user')->assertJsonCount(2, 'data.roles');
        DB::flushQueryLog();
        $first = $this->getJson('/api/v1/workspace/members')->assertOk()->assertJsonPath('data.total', 106)->assertJsonCount(20, 'data.items')->json('data.items');
        $smallQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $all = $this->getJson('/api/v1/workspace/members?perPage=100')->assertOk()->assertJsonCount(100, 'data.items')->json('data.items');
        $this->assertSame($smallQueries, count(DB::getQueryLog()), '讀取量不應逐成員追加SQL');
        $second = $this->getJson('/api/v1/workspace/members?perPage=100&page=2')->assertOk()->assertJsonCount(6, 'data.items')->json('data.items');
        $this->assertSame([], array_intersect(array_column($all, 'id'), array_column($second, 'id')));
        $ownerRow = collect($first)->firstWhere('id', $owner['user']['id']);
        $this->assertFalse($ownerRow['canChange']);
        $this->assertSame('owner', $ownerRow['roleId']);
        $this->getJson('/api/v1/workspace/members?search=Person%20104')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $users[104]->id);
        $this->getJson('/api/v1/workspace/members?perPage=101')->assertStatus(400);
        $this->getJson('/api/v1/workspace/members', ['X-Workspace-ID' => 'other'])->assertStatus(409);
        $this->rpc('updateWorkspace', ['name' => 'Renamed', 'expectedRevision' => 0])->assertOk();
        $this->getJson('/api/v1/workspace/settings')->assertOk()->assertJsonPath('data.workspace.revision', 1);
        $this->rpc('updateWorkspace', ['name' => 'Conflict', 'expectedRevision' => 0])->assertStatus(409);
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $users[0]->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson('/api/v1/workspace/member-roles')->assertOk()->assertJsonCount(2, 'data.roles');
        $this->getJson('/api/v1/workspace/members?search=Person%20002')->assertOk()->assertJsonPath('data.items.0.canChange', true);
        $this->rpc('setWorkspaceMemberRole', ['userId' => $owner['user']['id'], 'roleId' => 'user', 'expectedRevision' => 0])->assertStatus(403);
        $this->rpc('setWorkspaceMemberRole', ['userId' => $users[2]->id, 'roleId' => 'manager', 'expectedRevision' => 0])->assertOk();
        $this->getJson('/api/v1/workspace/members?search=Person%20002')->assertOk()->assertJsonPath('data.items.0.roleId', 'manager')->assertJsonPath('data.items.0.revision', 1);
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $users[1]->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson('/api/v1/workspace/settings')->assertOk()->assertJsonMissingPath('data.members');
        $this->getJson('/api/v1/workspace/members')->assertStatus(403);
        $this->getJson('/api/v1/workspace/member-roles')->assertStatus(403);
        Auth::logout();
        $outside = $this->postJson('/api/auth/register', ['name' => 'Other Owner', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Other'])->assertOk()->json('data');
        $this->getJson('/api/v1/workspace/members')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $outside['user']['id']);
        $this->getJson('/api/v1/workspace/settings', ['X-Workspace-ID' => $owner['workspace']['id']])->assertStatus(409);
    }
}
