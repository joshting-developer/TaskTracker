<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 零工作區帳號的登入與租戶隔離, 全部使用合成資料 */
trait AccountWithoutWorkspaceCases
{
    /**
     * 建立合成帳號並停用其唯一成員資格
     *
     * @return array<string,mixed> 原帳號與空間身分
     */
    private function detachedActor(): array
    {
        $actor = $this->postJson('/api/auth/register', ['name' => 'Detached', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-detached-password', 'workspaceName' => 'Previous'])->assertOk()->json('data');
        WorkspaceMember::where('user_id', $actor['user']['id'])->update(['disabled_at' => now()]);

        return $actor;
    }

    /**
     * current清除失效scope, 正確帳密仍能登入且沒有任何Workspace權限
     *
     * @return void
     */
    public function test_detached_current_and_login_preserve_account_without_scope(): void
    {
        $actor = $this->detachedActor();
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.workspace', null)->assertJsonPath('data.permissions', [])->assertSessionMissing('workspace_id');
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => $actor['user']['email'], 'password' => 'isolated-detached-password'])->assertOk()->assertJsonPath('data.user.id', $actor['user']['id'])->assertJsonPath('data.workspace', null);
        $this->postJson('/api/auth/workspaces', ['read' => true])->assertOk()->assertJsonPath('data.workspaces', []);
        $this->getJson('/api/v1/projects')->assertStatus(401);
        $this->getJson('/api/v1/todos')->assertStatus(401);
        $this->postJson('/api/rpc/createProject', ['requestId' => (string) Str::uuid(), 'name' => 'Not allowed', 'code' => 'DENIED'])->assertStatus(401);
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.user.id', $actor['user']['id']);
    }

    /**
     * 沒有空間可編輯本人資料且重播安全, 原Workspace RPC仍拒絕
     *
     * @return void
     */
    public function test_detached_profile_is_account_scoped_and_replay_safe(): void
    {
        $actor = $this->detachedActor();
        $profile = $this->postJson('/api/auth/getProfile', ['read' => true])->assertOk()->json('data');
        $input = ['name' => 'Renamed detached', 'expectedRevision' => $profile['revision'], 'requestId' => (string) Str::uuid(), 'userId' => (string) Str::uuid(), 'extra' => ['a' => 1, 'b' => 2]];
        $first = $this->postJson('/api/auth/updateProfile', $input)->assertOk()->json('data');
        $this->assertSame($actor['user']['id'], $first['id']);
        $this->postJson('/api/auth/updateProfile', [...$input, 'extra' => ['b' => 2, 'a' => 1]])->assertOk()->assertJsonPath('data.revision', $first['revision']);
        $this->postJson('/api/auth/updateProfile', [...$input, 'name' => 'Different'])->assertStatus(409);
        $this->postJson('/api/auth/updateProfile', [...$input, 'requestId' => (string) Str::uuid()])->assertStatus(409);
        $this->postJson('/api/auth/getProfile', ['read' => true])->assertOk()->assertJsonPath('data.name', 'Renamed detached');
        $this->postJson('/api/rpc/getProfile', ['read' => true])->assertStatus(401);
        User::whereKey($actor['user']['id'])->update(['session_epoch' => 1]);
        $this->postJson('/api/auth/updateProfile', $input)->assertStatus(401);
    }

    /**
     * 無空間帳號可建立自己的空間, 不恢復舊停用資格
     *
     * @return void
     */
    public function test_detached_actor_can_create_workspace_without_reactivating_old_membership(): void
    {
        $actor = $this->detachedActor();
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.workspace', null);
        $result = $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Mine', 'requestId' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.role', 'owner')->json('data');
        $this->assertNotSame($actor['workspace']['id'], $result['workspace']['id']);
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $actor['workspace']['id']])->assertStatus(404);
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame(1, WorkspaceMember::where('user_id', $actor['user']['id'])->whereNotNull('disabled_at')->count());
    }

    /**
     * current只查目前身分而非所有空間, 未驗證及舊登入不得進入帳號設定
     *
     * @return void
     */
    public function test_account_scope_keeps_email_and_session_guards_and_minimal_reads(): void
    {
        $actor = $this->detachedActor();
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.workspace', null);
        foreach (['projects', 'issues', 'time_entries', 'mail_deliveries'] as $table) {
            $this->assertStringNotContainsString($table, implode(' ', $sql));
        }
        User::whereKey($actor['user']['id'])->update(['email_verification_required' => true, 'email_verified_at' => null]);
        $this->postJson('/api/auth/getProfile', ['read' => true])->assertStatus(403);
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Denied', 'requestId' => (string) Str::uuid()])->assertStatus(403);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/getProfile', ['read' => true])->assertStatus(401);
    }
}
