<?php

namespace Tests\Concerns;

use App\Models\MailDelivery;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\MailDeliveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** 邀請先建立帳號Session, 再由受邀者決定加入或拒絕 */
trait InvitationDecisionCases
{
    /** 建立已驗證的隔離邀請者及單次邀請
     * @return array 測試資格與連結
     */
    private function decisionFixture(): array
    {
        $this->rawRegistration = false;
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-decision-password', 'workspaceName' => 'Inviting'])->assertOk()->json('data');
        $email = Str::uuid().'@example.test';
        $invite = $this->postJson('/api/rpc/inviteMember', ['email' => $email, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        $this->rawRegistration = true;

        return [$owner, $email, $invite];
    }

    /** 新受邀帳號不預先加入, 拒絕不刪帳號並仍可建立自己的空間
     * @return void
     */
    public function test_new_invitee_can_decline_without_membership_and_create_own_workspace(): void
    {
        Queue::fake();
        Mail::fake();
        [$owner,$email,$invite] = $this->decisionFixture();
        $token = ['inviteToken' => $invite['token']];
        $this->postJson('/api/auth/invitation', $token)->assertOk()->assertJsonPath('data.authMode', 'register');
        $user = $this->postJson('/api/auth/register', [...$token, 'name' => 'Invitee', 'email' => $email, 'password' => 'isolated-decision-password'])->assertOk()->assertJsonPath('data.workspace', null)->assertJsonPath('data.permissions', [])->json('data.user');
        $this->assertDatabaseMissing('workspace_members', ['user_id' => $user['id']]);
        $this->assertNotNull(User::find($user['id'])->email_verified_at);
        $this->postJson('/api/auth/declineWorkspaceInvite', $token)->assertOk()->assertJsonPath('data.declined', true);
        $delivery = MailDelivery::where('kind', 'invitation')->where('invitation_id', WorkspaceInvitation::where('public_id', hash('sha256', $invite['token']))->value('id'))->firstOrFail();
        $this->app->make(MailDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('skipped', $delivery->fresh()->status);
        Mail::assertNothingSent();
        $this->postJson('/api/auth/declineWorkspaceInvite', $token)->assertOk();
        $this->postJson('/api/auth/acceptWorkspaceInvite', $token)->assertStatus(400);
        $this->postJson('/api/auth/invitation', $token)->assertOk()->assertJsonPath('data.authMode', null)->assertJsonPath('data.declined', true);
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.user.id', $user['id'])->assertJsonPath('data.workspace', null);
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Mine', 'requestId' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.role', 'owner');
        $this->assertDatabaseMissing('workspace_members', ['user_id' => $user['id'], 'workspace_id' => WorkspaceInvitation::where('public_id', hash('sha256', $invite['token']))->value('workspace_id')]);
    }

    /** 已存在的受邀者先登入, 明確接受才建立成員資格
     * @return void
     */
    public function test_existing_invitee_login_never_accepts_implicitly(): void
    {
        [$owner,$email,$invite] = $this->decisionFixture();
        $this->rawRegistration = false;
        $account = $this->postJson('/api/auth/register', ['name' => 'Existing', 'email' => $email, 'password' => 'isolated-decision-password', 'workspaceName' => 'Original'])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        $token = ['inviteToken' => $invite['token']];
        $this->postJson('/api/auth/invitation', $token)->assertOk()->assertJsonPath('data.authMode', 'login');
        $this->postJson('/api/auth/login', [...$token, 'email' => $email, 'password' => 'wrong'])->assertStatus(401);
        $this->postJson('/api/auth/login', [...$token, 'email' => $email, 'password' => 'isolated-decision-password'])->assertOk()->assertJsonPath('data.workspace.id', $account['workspace']['id']);
        $this->assertSame(1, WorkspaceMember::where('user_id', $account['user']['id'])->count());
        $this->postJson('/api/auth/acceptWorkspaceInvite', $token)->assertOk()->assertJsonPath('data.workspace.id', $owner['workspace']['id']);
        $this->postJson('/api/auth/acceptWorkspaceInvite', $token)->assertOk();
        $this->assertSame(2, WorkspaceMember::where('user_id', $account['user']['id'])->count());
        $this->postJson('/api/auth/declineWorkspaceInvite', $token)->assertStatus(409);
    }

    /** 邀請者撤權後舊token不可用, 無效token不查受邀地址帳號
     * @return void
     */
    public function test_inviter_permission_is_rechecked_and_invalid_tokens_do_not_probe_accounts(): void
    {
        [$owner,$email,$invite] = $this->decisionFixture();
        WorkspaceMember::where('user_id', $owner['user']['id'])->update(['disabled_at' => now()]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });
        $this->postJson('/api/auth/invitation', ['inviteToken' => $invite['token'], 'email' => $email])->assertStatus(400);
        $this->assertFalse(collect($queries)->contains(fn ($sql) => str_contains($sql, 'from "users"') || str_contains($sql, 'from `users`')));
        $this->postJson('/api/auth/register', ['inviteToken' => $invite['token'], 'email' => $email, 'name' => 'No', 'password' => 'isolated-decision-password'])->assertStatus(400);
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }
}
