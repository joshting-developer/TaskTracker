<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** 驗證同帳號多租戶資格、邀請單次接受及舊頁面拒寫。 */
class WorkspaceMembershipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 建立一個有自己空間的測試帳號。
     *
     * @param  string  $email  測試Email
     * @return array 可信帳號資格
     */
    private function register(string $email): array
    {
        Auth::logout();

        return $this->postJson('/api/auth/register', [
            'email' => $email,
            'name' => $email,
            'password' => 'multi-workspace-test',
            'workspaceName' => $email,
        ])->assertOk()->json('data');
    }

    /**
     * 以本人與對應工作區切換測試Session。
     *
     * @param  array  $identity  已註冊帳號
     * @return void
     */
    private function signIn(array $identity): void
    {
        $this->actingAs(User::findOrFail($identity['user']['id']))->withSession(['workspace_id' => $identity['workspace']['id']]);
    }

    /**
     * 跨區邀請只新增member、不新增user、不提升角色或帶入專案。
     *
     * @return void
     */
    public function test_existing_account_accept_switch_replay_and_scope_guard(): void
    {
        $first = $this->register('multi-first@example.test');
        $second = $this->register('multi-second@example.test');
        $this->postJson('/api/rpc/createProject', [
            'requestId' => 'membership-project',
            'name' => '另一個空間專案',
            'code' => 'OTHER',
        ])->assertOk();
        $invite = $this->postJson('/api/rpc/inviteMember', ['email' => $first['user']['email']])->assertOk()->json('data');
        $input = ['inviteToken' => $invite['token']];
        $this->postJson('/api/auth/acceptWorkspaceInvite', $input)->assertStatus(403);
        $this->signIn($first);
        $this->postJson('/api/auth/workspaces', ['requestId' => 'membership-read'])->assertOk()->assertJsonCount(1, 'data.workspaces');
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $second['workspace']['id']])->assertStatus(404);
        $this->postJson('/api/auth/invitation', $input)->assertOk()->assertJsonPath('data.email', $first['user']['email']);
        $this->postJson('/api/auth/acceptWorkspaceInvite', $input)->assertOk()->assertJsonPath('data.workspace.id', $second['workspace']['id'])->assertJsonPath('data.role', 'user');
        $this->assertDatabaseCount('users', 2);
        $this->postJson('/api/rpc/getWorkspace', ['requestId' => 'membership-read'])->assertOk()->assertJsonCount(0, 'data.projects');
        $this->postJson('/api/auth/acceptWorkspaceInvite', $input)->assertOk();
        $this->postJson('/api/auth/workspaces', ['requestId' => 'membership-read'])->assertOk()->assertJsonCount(2, 'data.workspaces');
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $first['workspace']['id']])->assertOk()->assertJsonPath('data.role', 'owner');
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => 'unknown'])->assertStatus(404);
        $this->withHeader('X-Workspace-ID', $second['workspace']['id'])->postJson('/api/rpc/createProject', [
            'name' => '不能錯寫',
            'code' => 'STALE',
        ])->assertStatus(409)->assertJsonPath('error.code', 'WORKSPACE_CHANGED');
        $this->withHeader('X-Workspace-ID', $first['workspace']['id'])->postJson('/api/rpc/getWorkspace', ['requestId' => 'membership-read'])->assertOk()->assertJsonCount(0, 'data.projects');
        $this->flushHeaders();
        $this->signIn($second);
        $settings = $this->getJson('/api/v1/workspace/members')->assertOk()->json('data');
        $member = collect($settings['items'])->firstWhere('id', $first['user']['id']);
        $this->postJson('/api/rpc/setWorkspaceMemberRole', [
            'requestId' => 'membership-role',
            'userId' => $first['user']['id'],
            'roleId' => 'manager',
            'expectedRevision' => $member['revision'],
        ])->assertOk();
        $this->signIn($first);
        $this->postJson('/api/auth/acceptWorkspaceInvite', $input)->assertOk()->assertJsonPath('data.role', 'manager');
        $tenant = (new RecordStore)->forWorkspace($second['workspace']['id']);
        $member = $tenant->require('members', $first['user']['id']);
        $tenant->put('members', $member['id'], [
            ...$member,
            'disabledAt' => now()->utc()->toISOString(),
        ]);
        $this->postJson('/api/auth/workspaces', ['requestId' => 'membership-read'])->assertOk()->assertJsonCount(1, 'data.workspaces');
        $this->postJson('/api/auth/acceptWorkspaceInvite', $input)->assertStatus(403);
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $second['workspace']['id']])->assertStatus(404);
    }

    /**
     * 登入可接受跨區邀請，錯誤密碼／過期token不消耗邀請。
     *
     * @return void
     */
    public function test_invited_login_and_expiry_preserve_account_and_memberships(): void
    {
        $first = $this->register('invite-first@example.test');
        $second = $this->register('invite-second@example.test');
        $invite = $this->postJson('/api/rpc/inviteMember', ['email' => $first['user']['email']])->assertOk()->json('data');
        Auth::logout();
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $invite['token']])->assertStatus(401);
        $this->postJson('/api/auth/login', [
            'email' => $first['user']['email'],
            'password' => 'wrong',
            'inviteToken' => $invite['token'],
        ])->assertStatus(401);
        $this->postJson('/api/auth/login', [
            'email' => $first['user']['email'],
            'password' => 'multi-workspace-test',
            'inviteToken' => $invite['token'],
        ])->assertOk()->assertJsonPath('data.workspace.id', $second['workspace']['id']);
        $this->postJson('/api/auth/workspaces', ['requestId' => 'membership-read'])->assertOk()->assertJsonCount(2, 'data.workspaces');
        $this->signIn($second);
        $this->postJson('/api/rpc/inviteMember', ['email' => $first['user']['email']])->assertStatus(409);
        $third = $this->register('invite-third@example.test');
        $expired = $this->postJson('/api/rpc/inviteMember', ['email' => $first['user']['email']])->assertOk()->json('data');
        $store = new RecordStore;
        $id = hash('sha256', $expired['token']);
        $record = $store->require('invites', $id);
        $store->put('invites', $id, [
            ...$record,
            'expiresAt' => now()->subDay()->utc()->toISOString(),
        ]);
        $this->signIn($first);
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $expired['token']])->assertStatus(400);
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $third['workspace']['id']])->assertStatus(404);
        $this->assertDatabaseCount('users', 3);
    }
}
