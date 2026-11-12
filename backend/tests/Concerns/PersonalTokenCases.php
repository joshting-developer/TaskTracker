<?php

namespace Tests\Concerns;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** memory與MySQL共用PAT的真實HTTP安全及資料契約。 */
trait PersonalTokenCases
{
    private function tokenRegister(): array
    {
        Auth::forgetGuards();

        return $this->postJson('/api/auth/register', ['name' => 'token', 'email' => Str::uuid().'@example.test', 'password' => 'token-test-password', 'workspaceName' => 'tokens'])->assertOk()->json('data');
    }

    private function tokenIssue(array $extra = []): array
    {
        return $this->postJson('/api/v1/personal-tokens', ['name' => 'tool', 'abilities' => ['read'], 'requestId' => (string) Str::uuid(), ...$extra])->assertOk()->json('data');
    }

    private function bearerIdentity(string $plain, int $status = 200)
    {
        Auth::forgetGuards();

        return $this->getJson('/api/public/v1/me', ['Authorization' => 'Bearer '.$plain])->assertStatus($status)->assertCookieMissing(config('session.cookie'));
    }

    public function test_token_once_hash_scope_expiry_revoke_and_workspace_qualification(): void
    {
        $identity = $this->tokenRegister();
        $requestId = (string) Str::uuid();
        $created = $this->tokenIssue(['requestId' => $requestId, 'abilities' => ['write']]);
        $plain = $created['plainTextToken'];
        $model = PersonalAccessToken::findOrFail($created['token']['id']);
        $this->assertNotSame($plain, $model->token);
        $this->assertSame(hash('sha256', explode('|', $plain, 2)[1]), $model->token);
        $this->assertTrue($model->can('read'));
        $this->assertTrue($model->can('write'));
        $this->assertSame(now('Asia/Taipei')->startOfDay()->addMonthNoOverflow()->utc()->toISOString(), $created['token']['expiresAt']);
        $this->getJson('/api/v1/personal-tokens')->assertOk()->assertDontSee($plain, false)->assertDontSee($model->token, false)->assertJsonPath('data.total', 1);
        $this->postJson('/api/v1/personal-tokens', ['name' => 'tool', 'abilities' => ['write'], 'requestId' => $requestId])->assertStatus(409);
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $identity['user']['id'])->count());
        foreach ([['abilities' => ['*']], ['expiresAt' => '2100-01-01'], ['expiresAt' => now()->subDay()->toDateString()], ['expiresAt' => null], ['abilities' => []]] as $invalid) {
            $this->postJson('/api/v1/personal-tokens', ['name' => 'invalid', 'abilities' => ['read'], 'requestId' => (string) Str::uuid(), ...$invalid])->assertStatus(400);
        }
        $this->bearerIdentity($plain)->assertJsonPath('data.workspaceId', $identity['workspace']['id']);
        $this->assertNull($model->fresh()->last_used_at); // 身分讀取不得每次回寫Token。
        $this->bearerIdentity('invalid', 401);
        Auth::forgetGuards();
        $this->getJson('/api/public/v1/me')->assertStatus(401); // Session不得冒充PAT
        $this->actingAs(User::findOrFail($identity['user']['id']))->withSession(['workspace_id' => $identity['workspace']['id']]);
        $read = $this->tokenIssue();
        $this->assertFalse(PersonalAccessToken::findOrFail($read['token']['id'])->can('write'));
        $this->postJson('/api/v1/personal-tokens/'.$created['token']['id'].'/revoke')->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->bearerIdentity($plain, 401);
        PersonalAccessToken::whereKey($read['token']['id'])->update(['expires_at' => now()->subSecond()]);
        $this->bearerIdentity($read['plainTextToken'], 401);
        $this->actingAs(User::findOrFail($identity['user']['id']))->withSession(['workspace_id' => $identity['workspace']['id']]);
        $active = $this->tokenIssue();
        WorkspaceMember::where('user_id', $identity['user']['id'])->update(['disabled_at' => now()]);
        $this->bearerIdentity($active['plainTextToken'], 401);
    }

    public function test_tokens_are_owner_and_workspace_scoped_paginated_and_not_rpc_credentials(): void
    {
        $first = $this->tokenRegister();
        $issued = $this->tokenIssue();
        $template = PersonalAccessToken::findOrFail($issued['token']['id']);
        for ($n = 0; $n < 22; $n++) {
            $copy = $template->replicate();
            $copy->token = hash('sha256', Str::uuid());
            $copy->issuance_request_id = (string) Str::uuid();
            $copy->save();
        }
        $this->getJson('/api/v1/personal-tokens?page=1')->assertOk()->assertJsonCount(20, 'data.items')->assertJsonPath('data.total', 23);
        $this->getJson('/api/v1/personal-tokens?page=2')->assertOk()->assertJsonCount(3, 'data.items');
        $second = $this->tokenRegister();
        $this->getJson('/api/v1/personal-tokens')->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson('/api/v1/personal-tokens/'.$issued['token']['id'].'/revoke')->assertStatus(404);
        $this->getJson('/api/v1/personal-tokens', ['X-Workspace-ID' => $first['workspace']['id']])->assertStatus(409);
        // 同一人加入第二個Workspace，Token管理仍只顯示目前Workspace。
        $invitation = $this->postJson('/api/rpc/inviteMember', ['email' => $first['user']['email'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->actingAs(User::findOrFail($first['user']['id']))->withSession(['workspace_id' => $first['workspace']['id']]);
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $invitation['token']])->assertOk();
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $second['workspace']['id']])->assertOk();
        $this->getJson('/api/v1/personal-tokens')->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson('/api/v1/personal-tokens/'.$issued['token']['id'].'/revoke')->assertStatus(404);
        $boundSecond = $this->tokenIssue();
        $this->bearerIdentity($boundSecond['plainTextToken'])->assertJsonPath('data.workspaceId', $second['workspace']['id']);
        // 沒有Session的Bearer不能管理Token或呼叫內部RPC。
        Auth::logout();
        $this->flushSession();
        Auth::forgetGuards();
        $this->postJson('/api/rpc/createProject', ['name' => 'no', 'code' => 'NO'], ['Authorization' => 'Bearer '.$issued['plainTextToken']])->assertStatus(401);
        $this->getJson('/api/v1/personal-tokens', ['Authorization' => 'Bearer '.$issued['plainTextToken']])->assertStatus(401);
        // PAT目標由自身Workspace決定，不受另一個登入Session影響。
        $this->actingAs(User::findOrFail($second['user']['id']))->withSession(['workspace_id' => $second['workspace']['id']]);
        $this->bearerIdentity($issued['plainTextToken'])->assertJsonPath('data.userId', $first['user']['id'])->assertJsonPath('data.workspaceId', $first['workspace']['id']);
    }
}
