<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceMemberStatusEvent;
use App\Repositories\WorkspaceMemberStatusRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** 停用只改同空間成員資格, 不刪帳號或歷史 */
trait WorkspaceMemberStatusCases
{
    /** 建立Owner、一般成員及既有指派
     * @return array 合成fixture
     */
    private function statusFixture(): array
    {
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-status-password', 'workspaceName' => 'Status'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Project', 'code' => 'STATUS', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $invite = $this->postJson('/api/rpc/inviteMember', ['email' => Str::uuid().'@example.test', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        $member = $this->postJson('/api/auth/register', ['name' => 'Member', 'email' => $invite['email'], 'password' => 'isolated-status-password', 'inviteToken' => $invite['token']])->assertOk()->json('data');
        $this->statusSignIn($owner);
        $acl = $this->postJson('/api/rpc/getProjectMembers', ['projectId' => $project['id']])->assertOk()->json('data');
        $this->postJson('/api/rpc/setProjectMember', ['projectId' => $project['id'], 'userId' => $member['user']['id'], 'roleId' => 'developer', 'expectedRevision' => $acl['revision'], 'requestId' => (string) Str::uuid()])->assertOk();
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Keep assignment', 'assignee' => $member['user']['id'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$owner, $member, $project, $issue, $invite];
    }

    /** 切換合成fixture本人, 不增加其資格
     * @param  array  $identity  已建立的可信fixture
     * @return void
     */
    private function statusSignIn(array $identity): void
    {
        $this->actingAs(User::findOrFail($identity['user']['id']))->withSession(['workspace_id' => $identity['workspace']['id'], 'session_epoch' => 0]);
    }

    /** 狀態寫入使用最新版本及唯一requestId
     * @param  array  $member  目標fixture
     * @param  string  $status  啟用或停用
     * @return array 寫入payload
     */
    private function statusPayload(array $member, string $status): array
    {
        return ['userId' => $member['user']['id'], 'status' => $status, 'expectedRevision' => (int) WorkspaceMember::where('user_id', $member['user']['id'])->whereHas('workspace', fn ($q) => $q->where('public_id', $member['workspace']['id']))->firstOrFail()->role_revision, 'requestId' => (string) Str::uuid()];
    }

    /** 只計此fixture資格的事件, 不讀其他合成帳號歷史
     * @param  array  $member  目標fixture
     * @return int 事件數
     */
    private function statusEventCount(array $member): int
    {
        $id = WorkspaceMember::where('user_id', $member['user']['id'])->whereHas('workspace', fn ($q) => $q->where('public_id', $member['workspace']['id']))->value('id');

        return WorkspaceMemberStatusEvent::where('member_id', $id)->count();
    }

    /** 撤權、重播、歷史指派及恢復後原ACL皆保留
     * @return void
     */
    public function test_disable_restore_preserve_account_assignment_and_project_acl(): void
    {
        [$owner,$member,$project,$issue,$invite] = $this->statusFixture();
        $input = $this->statusPayload($member, 'disabled');
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertOk()->assertJsonPath('data.status', 'disabled');
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertOk();
        $this->assertSame(1, $this->statusEventCount($member));
        $this->assertDatabaseHas('users', ['id' => $member['user']['id']]);
        $detail = $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'])->assertOk()->assertJsonPath('data.assignee', $member['user']['id'])->assertJsonPath('data.assigneeDisabled', true);
        $this->assertSame('Member', $detail->json('data.assigneeName'));
        $this->getJson('/api/v1/workspace/members?status=disabled')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.canSetStatus', true);
        $this->getJson('/api/v1/projects/'.$project['id'].'/member-options?search=Member')->assertOk()->assertJsonPath('data.total', 0);
        $this->statusSignIn($member);
        $this->getJson('/api/v1/projects/'.$project['id'])->assertStatus(401);
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $invite['token']])->assertStatus(403);
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.workspace', null);
        $this->statusSignIn($owner);
        $restore = $this->statusPayload($member, 'active');
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $restore)->assertOk()->assertJsonPath('data.disabledAt', null);
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertOk();
        $this->assertNull(WorkspaceMember::where('user_id', $member['user']['id'])->first()->disabled_at);
        $this->assertSame(2, $this->statusEventCount($member));
        $this->statusSignIn($member);
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'])->assertOk()->assertJsonPath('data.assigneeDisabled', false);
    }

    /** Owner、本人及跨空間不可由一般資格操作停用
     * @return void
     */
    public function test_status_authorization_owner_self_cross_workspace_and_conflict_guards(): void
    {
        [$owner,$member] = $this->statusFixture();
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $this->statusPayload($owner, 'disabled'))->assertStatus(403);
        $input = $this->statusPayload($member, 'disabled');
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', [...$input, 'expectedRevision' => 99])->assertStatus(409);
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', [...$input, 'status' => 'invalid'])->assertStatus(400);
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', [...$input, 'userId' => (string) Str::uuid()])->assertStatus(404);
        $this->statusSignIn($member);
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertStatus(403);
        $this->getJson('/api/v1/workspace/members?status=disabled')->assertStatus(403);
        $this->assertSame(0, $this->statusEventCount($member));
    }

    /** 權限撤銷後不重播舊成功結果, 事件失敗回滾狀態及receipt
     * @return void
     */
    public function test_status_receipts_reauthorize_and_event_failure_rolls_back(): void
    {
        [$owner,$member] = $this->statusFixture();
        $input = $this->statusPayload($member, 'disabled');
        $real = $this->app->make(WorkspaceMemberStatusRepository::class);
        $this->mock(WorkspaceMemberStatusRepository::class, function ($mock) use ($real) {
            $mock->shouldReceive('member')->andReturnUsing(fn (...$args) => $real->member(...$args));
            $mock->shouldReceive('update')->andReturnUsing(fn (...$args) => $real->update(...$args));
            $calls = 0;
            $mock->shouldReceive('record')->twice()->andReturnUsing(function (...$args) use ($real, &$calls) {
                if (++$calls === 1) {
                    throw new \RuntimeException('synthetic audit storage failure');
                }
                $real->record(...$args);
            });
        });
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertStatus(500);
        $this->assertNull(WorkspaceMember::where('user_id', $member['user']['id'])->first()->disabled_at);
        $this->assertSame(0, $this->statusEventCount($member));
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertOk();
        WorkspaceMember::where('user_id', $owner['user']['id'])->update(['role' => 'user']);
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertStatus(403);
    }

    /** PAT及Session只撤銷一個空間, 恢復不改原ACL或歷史
     * @return void
     */
    public function test_disabled_membership_rejects_pat_and_preserves_other_workspace_and_history(): void
    {
        [$owner,$member,$project,$issue] = $this->statusFixture();
        $this->statusSignIn($member);
        $comment = $this->postJson('/api/rpc/addComment', ['id' => $issue['id'], 'content' => 'Preserved comment', 'expectedRevision' => $issue['revision'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $entry = $this->postJson('/api/rpc/logTime', ['issueId' => $issue['id'], 'workDate' => '2026-11-15', 'minutes' => 15, 'note' => 'Preserved time', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'member', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $other = $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Other membership', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $otherToken = $this->postJson('/api/v1/personal-tokens', ['name' => 'other', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $this->statusSignIn($owner);
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $this->statusPayload($member, 'disabled'))->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/public/v1/me', ['Authorization' => 'Bearer '.$token])->assertStatus(401);
        Auth::forgetGuards();
        $this->getJson('/api/public/v1/me', ['Authorization' => 'Bearer '.$otherToken])->assertOk()->assertJsonPath('data.workspaceId', $other['workspace']['id']);
        $this->statusSignIn($member);
        $this->postJson('/api/auth/workspaces', ['read' => true])->assertOk()->assertJsonCount(1, 'data.workspaces')->assertJsonPath('data.workspaces.0.id', $other['workspace']['id']);
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $other['workspace']['id']])->assertOk();
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonPath('data.total', 0);
        $this->statusSignIn($owner);
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'].'/comments')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'].'/time')->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $this->statusPayload($member, 'active'))->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/public/v1/me', ['Authorization' => 'Bearer '.$token])->assertOk();
    }
}
