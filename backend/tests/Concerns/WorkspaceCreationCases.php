<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCreationRequest;
use App\Models\WorkspaceMember;
use App\Repositories\WorkspaceCreationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 既有帳號建立空間的資格、重播與租戶隔離 */
trait WorkspaceCreationCases
{
    /**
     * 建立已驗證的合成帳號與原工作空間
     *
     * @return array<string,mixed>
     */
    private function creationActor(): array
    {
        return $this->postJson('/api/auth/register', ['name' => 'Creation actor', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-creation-password', 'workspaceName' => 'Original'])->assertOk()->json('data');
    }

    /**
     * 一般成員可建立並成為新Owner, 不新增User或改動原空間角色
     *
     * @return void
     */
    public function test_member_creates_own_workspace_and_keeps_original_role(): void
    {
        $actor = $this->creationActor();
        $old = Workspace::where('public_id', $actor['workspace']['id'])->firstOrFail();
        WorkspaceMember::where('workspace_id', $old->id)->where('user_id', $actor['user']['id'])->update(['role' => 'user']);
        $users = User::count();
        $input = ['workspaceName' => 'My new space', 'requestId' => (string) Str::uuid(), 'userId' => (string) Str::uuid(), 'role' => 'manager', 'workspaceId' => $old->public_id];
        $result = $this->postJson('/api/auth/createWorkspace', $input)->assertOk()->assertJsonPath('data.role', 'owner')->json('data');
        $this->assertNotSame($old->public_id, $result['workspace']['id']);
        $this->assertSame($users, User::count());
        $this->assertSame('user', WorkspaceMember::where('workspace_id', $old->id)->where('user_id', $actor['user']['id'])->value('role'));
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.workspace.id', $result['workspace']['id']);
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $old->public_id])->assertOk()->assertJsonPath('data.role', 'user');
    }

    /**
     * 重播僅回同一結果, 不同名稱衝突, 撤銷資格後不重新授權
     *
     * @return void
     */
    public function test_creation_replay_conflict_and_revocation_are_scoped_to_actor(): void
    {
        $actor = $this->creationActor();
        $input = ['workspaceName' => 'Once', 'requestId' => (string) Str::uuid()];
        $first = $this->postJson('/api/auth/createWorkspace', $input)->assertOk()->json('data.workspace.id');
        $this->postJson('/api/auth/createWorkspace', $input)->assertOk()->assertJsonPath('data.workspace.id', $first);
        $this->assertSame(1, WorkspaceCreationRequest::where('user_id', $actor['user']['id'])->count());
        $this->postJson('/api/auth/createWorkspace', [...$input, 'workspaceName' => 'Changed'])->assertStatus(409);
        $new = Workspace::where('public_id', $first)->firstOrFail();
        WorkspaceMember::where('workspace_id', $new->id)->where('user_id', $actor['user']['id'])->update(['disabled_at' => now()]);
        $this->postJson('/api/auth/createWorkspace', $input)->assertStatus(403);
        $this->assertNotNull(WorkspaceMember::where('workspace_id', $new->id)->value('disabled_at'));
    }

    /**
     * 已刪除空間的receipt保留為空結果, 重試不能意外再建
     *
     * @return void
     */
    public function test_deleted_workspace_receipt_is_not_recreated(): void
    {
        $actor = $this->creationActor();
        $input = ['workspaceName' => 'Deleted', 'requestId' => (string) Str::uuid()];
        $id = $this->postJson('/api/auth/createWorkspace', $input)->assertOk()->json('data.workspace.id');
        $new = Workspace::where('public_id', $id)->firstOrFail();
        WorkspaceMember::where('workspace_id', $new->id)->delete();
        $new->delete();
        $this->postJson('/api/auth/createWorkspace', $input)->assertStatus(404);
        $this->assertNull(WorkspaceCreationRequest::where('user_id', $actor['user']['id'])->value('workspace_id'));
        $this->assertDatabaseMissing('workspaces', ['public_id' => $id]);
    }

    /**
     * 匿名、未驗證與舊Session均不能建立, 壞名稱或識別碼不產生資料
     *
     * @return void
     */
    public function test_creation_requires_current_eligible_account_and_valid_input(): void
    {
        $input = ['workspaceName' => 'Denied', 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/auth/createWorkspace', $input)->assertStatus(401);
        $actor = $this->creationActor();
        foreach ([['workspaceName' => ''], ['workspaceName' => str_repeat('名', 61)], ['workspaceName' => []], ['requestId' => 'bad']] as $change) {
            $this->postJson('/api/auth/createWorkspace', [...$input, ...$change])->assertStatus(400);
        }
        $user = User::findOrFail($actor['user']['id']);
        $user->forceFill(['email_verification_required' => true, 'email_verified_at' => null])->save();
        $this->postJson('/api/auth/createWorkspace', $input)->assertStatus(403);
        $this->assertDatabaseMissing('workspace_creation_requests', ['user_id' => $user->id]);
    }

    /**
     * 重設密碼前的登入版本不能建立新的工作空間
     *
     * @return void
     */
    public function test_creation_rejects_session_from_before_password_reset(): void
    {
        $actor = $this->creationActor();
        User::whereKey($actor['user']['id'])->update(['session_epoch' => 1]);
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Stale', 'requestId' => (string) Str::uuid()])->assertStatus(401);
    }

    /**
     * 本人低頻建立入口仍受每分鐘五次限制
     *
     * @return void
     */
    public function test_workspace_creation_rate_limit_is_enforced(): void
    {
        $this->creationActor();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/createWorkspace', ['workspaceName' => '', 'requestId' => (string) Str::uuid()])->assertStatus(400);
        }
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Limited', 'requestId' => (string) Str::uuid()])->assertStatus(429);
    }

    /**
     * 新空間或receipt保存失敗時整筆回滾, 不留下沒有Owner的空間
     *
     * @return void
     */
    public function test_creation_rolls_back_when_receipt_storage_fails(): void
    {
        $actor = $this->creationActor();
        $this->partialMock(WorkspaceCreationRepository::class)->shouldReceive('saveCreation')->once()->andThrow(new \RuntimeException('Synthetic receipt failure'));
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Rollback only', 'requestId' => (string) Str::uuid()])->assertStatus(500);
        $this->assertDatabaseMissing('workspaces', ['name' => 'Rollback only']);
        $this->assertDatabaseMissing('workspace_creation_requests', ['user_id' => $actor['user']['id']]);
    }

    /**
     * 建立只讀帳號、單筆receipt及新空間資格, 不查任務或歷史
     *
     * @return void
     */
    public function test_creation_reads_no_project_or_activity_data(): void
    {
        $this->creationActor();
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Scoped', 'requestId' => (string) Str::uuid()])->assertOk();
        foreach (['projects', 'issues', 'time_entries', 'issue_comments', 'issue_history'] as $table) {
            $this->assertStringNotContainsString($table, implode(' ', $sql));
        }
        if (DB::getDriverName() === 'mysql') {
            $this->assertTrue(collect($sql)->contains(fn ($query) => str_contains($query, '`users`') && str_contains($query, 'for update')));
        }
    }
}
