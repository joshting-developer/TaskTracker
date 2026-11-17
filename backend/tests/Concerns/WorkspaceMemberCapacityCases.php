<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;

/** 人數上限由真實邀請接受與狀態入口驗證, 不修改正式資料 */
trait WorkspaceMemberCapacityCases
{
    /** 建立Owner與指定數量的人造成員
     * @param  int  $count  含Owner的有效成員數
     * @return array Owner身分與工作空間內部鍵
     */
    private function capacityFixture(int $count): array
    {
        $owner = $this->postJson('/api/auth/register', ['name' => 'Capacity owner', 'email' => Str::uuid().'@example.test', 'password' => 'capacity-test-password', 'workspaceName' => 'Capacity'])->assertOk()->json('data');
        $workspace = Workspace::where('public_id', $owner['workspace']['id'])->value('id');
        for ($i = 1; $i < $count; $i++) {
            $this->capacityMember($workspace);
        }

        return [$owner, $workspace];
    }

    /** 合成資格只用於準備既有成員與停用／超額狀態
     * @param  int  $workspace  目標空間內部鍵
     * @param  bool  $disabled  是否停用
     * @return WorkspaceMember 已保存資格
     */
    private function capacityMember(int $workspace, bool $disabled = false): WorkspaceMember
    {
        $user = User::create(['id' => (string) Str::uuid(), 'name' => 'Member', 'email' => Str::uuid().'@example.test', 'password' => 'capacity-test-password']);

        return WorkspaceMember::create(['workspace_id' => $workspace, 'user_id' => $user->id, 'role' => 'user', 'joined_at' => now(), 'disabled_at' => $disabled ? now() : null]);
    }

    /** 第五人可加入, 第六人拒絕且邀請不消耗, 已有效成員重播不佔新位
     * @return void
     */
    public function test_capacity_acceptance_counts_owner_not_pending_and_preserves_failed_invitation(): void
    {
        [$owner, $workspace] = $this->capacityFixture(4);
        $other = Workspace::create(['public_id' => (string) Str::uuid(), 'name' => 'Unrelated workspace', 'created_at' => now()]);
        for ($i = 0; $i < 6; $i++) {
            $this->capacityMember($other->id);
        }
        $tokens = [];
        foreach ([1, 2] as $i) {
            $email = Str::uuid().'@example.test';
            $user = User::create(['id' => (string) Str::uuid(), 'name' => 'Invitee', 'email' => $email, 'password' => 'capacity-test-password']);
            $invite = $this->postJson('/api/rpc/inviteMember', ['email' => $email, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
            $tokens[] = [$user, $invite['token']];
        }
        $this->assertSame(4, WorkspaceMember::where('workspace_id', $workspace)->whereNull('disabled_at')->count());
        [$fifth, $token] = $tokens[0];
        $this->actingAs($fifth);
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $token])->assertOk();
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $token])->assertOk();
        [$sixth, $token] = $tokens[1];
        $this->actingAs($sixth);
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $token])->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');
        $this->assertNull(WorkspaceInvitation::where('public_id', hash('sha256', $token))->firstOrFail()->consumed_at);
        $this->assertFalse(WorkspaceMember::where('workspace_id', $workspace)->where('user_id', $sixth->id)->exists());
        $this->assertSame(5, WorkspaceMember::where('workspace_id', $workspace)->whereNull('disabled_at')->count());
    }

    /** 恢復受同一人數限制, 停用釋位, 舊超額空間仍可管理而不刪人
     * @return void
     */
    public function test_over_capacity_workspace_can_disable_and_restore_only_after_slot_is_free(): void
    {
        [$owner, $workspace] = $this->capacityFixture(6);
        $disabled = $this->capacityMember($workspace, true);
        $input = ['userId' => $disabled->user_id, 'status' => 'active', 'expectedRevision' => (int) $disabled->role_revision, 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertStatus(409);
        $this->getJson('/api/v1/workspace/members')->assertOk();
        $this->postJson('/api/rpc/createProject', ['name' => 'Still managed', 'code' => 'CAP', 'requestId' => (string) Str::uuid()])->assertOk();
        $members = WorkspaceMember::where('workspace_id', $workspace)->where('user_id', '!=', $owner['user']['id'])->whereNull('disabled_at')->take(2)->get();
        foreach ($members as $member) {
            $this->postJson('/api/rpc/setWorkspaceMemberStatus', ['userId' => $member->user_id, 'status' => 'disabled', 'expectedRevision' => (int) $member->role_revision, 'requestId' => (string) Str::uuid()])->assertOk();
            if ($member->id === $members[0]->id) {
                $this->postJson('/api/rpc/setWorkspaceMemberStatus', [...$input, 'requestId' => (string) Str::uuid()])->assertStatus(409);
            }
        }
        $input['requestId'] = (string) Str::uuid();
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertOk();
        $this->postJson('/api/rpc/setWorkspaceMemberStatus', $input)->assertOk();
        $this->assertSame(5, WorkspaceMember::where('workspace_id', $workspace)->whereNull('disabled_at')->count());
        $this->assertSame(7, WorkspaceMember::where('workspace_id', $workspace)->count());
    }
}
