<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** 驗證設定API的本人／租戶邊界、授權、版本與實際SQL保存。 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** 建立獨立擁有者帳號。
     * @param  string  $email  測試信箱
     * @return array
     */
    private function register(string $email): array
    {
        Auth::logout();

        return $this->postJson('/api/auth/register', [
            'name' => '設定測試',
            'email' => $email,
            'password' => 'settings-test-password',
            'workspaceName' => '測試空間',
        ])->assertOk()->json('data');
    }

    /** 呼叫具隨機requestId的設定API。
     * @param  string  $operation  固定操作名稱
     * @param  array  $input  原始輸入
     * @return TestResponse
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, [
            'requestId' => (string) Str::uuid(),
            ...$input,
        ]);
    }

    /** 用邀請加入同空間的一般成員。
     * @param  string  $email  成員測試信箱
     * @return array
     */
    private function join(string $email): array
    {
        $invite = $this->rpc('inviteMember', ['email' => $email])->assertOk()->json('data');
        Auth::logout();

        return $this->postJson('/api/auth/register', [
            'name' => '一般成員',
            'email' => $email,
            'password' => 'settings-test-password',
            'inviteToken' => $invite['token'],
        ])->assertOk()->json('data');
    }

    /** 驗證本人姓名、空白驗證、重試／衝突與私密欄位。
     * @return void
     */
    public function test_update_profile_success_only_self_and_revision_replay(): void
    {
        $this->rpc('getProfile')->assertStatus(401);
        $owner = $this->register('settings-owner@example.test');
        $hash = User::findOrFail($owner['user']['id'])->password;
        $this->rpc('getProfile')->assertJsonPath('data.revision', 0)->assertJsonMissing(['password' => $hash]);
        $input = [
            'name' => '新的顯示名稱',
            'expectedRevision' => 0,
            'requestId' => 'profile-change',
            'id' => 'forged-other-user',
        ];
        $this->rpc('updateProfile', $input)->assertOk()->assertJsonPath('data.id', $owner['user']['id'])->assertJsonPath('data.revision', 1);
        $this->rpc('updateProfile', $input)->assertOk()->assertJsonPath('data.revision', 1);
        $this->rpc('updateProfile', [
            ...$input,
            'name' => '不同重試',
        ])->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_CONFLICT');
        $this->rpc('updateProfile', [
            'name' => '過期覆寫',
            'expectedRevision' => 0,
        ])->assertStatus(409);
        $this->rpc('updateProfile', [
            'name' => ' ',
            'expectedRevision' => 1,
        ])->assertStatus(400);
        $this->rpc('getWorkspace')->assertJsonPath('data.meta.actor', '新的顯示名稱')->assertJsonPath('data.members.0.name', '新的顯示名稱');
        $this->assertDatabaseHas('users', [
            'id' => $owner['user']['id'],
            'name' => '新的顯示名稱',
            'email' => 'settings-owner@example.test',
            'password' => $hash,
            'profile_revision' => 1,
        ]);
    }

    /** 驗證空間改名與保護版本欄不被一般RecordStore寫入重設。
     * @return void
     */
    public function test_update_workspace_success_scope_validation_and_conflict(): void
    {
        $owner = $this->register('workspace-owner@example.test');
        $other = $this->register('workspace-other@example.test');
        $this->actingAs(User::findOrFail($owner['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $input = [
            'name' => '重新命名空間',
            'expectedRevision' => 0,
            'requestId' => 'rename-workspace',
            'workspaceId' => $other['workspace']['id'],
        ];
        $this->rpc('updateWorkspace', $input)->assertOk()->assertJsonPath('data.revision', 1);
        $this->rpc('updateWorkspace', $input)->assertOk();
        $this->rpc('updateWorkspace', [
            'name' => '舊表單',
            'expectedRevision' => 0,
        ])->assertStatus(409);
        $this->rpc('updateWorkspace', [
            'name' => str_repeat('字', 61),
            'expectedRevision' => 1,
        ])->assertStatus(400);
        $this->getJson('/api/v1/workspace/settings')->assertJsonPath('data.workspace.name', '重新命名空間')->assertJsonPath('data.workspace.revision', 1);
        $this->assertDatabaseHas('workspaces', [
            'public_id' => $other['workspace']['id'],
            'name' => '測試空間',
        ]);
        $store = new RecordStore;
        $record = $store->require('workspaces', $owner['workspace']['id']);
        $store->put('workspaces', $record['id'], $record);
        $this->getJson('/api/v1/workspace/settings')->assertJsonPath('data.workspace.revision', 1);
    }

    /** 驗證一般成員不能改空間／角色，升降權後Project ACL立即生效。
     * @return void
     */
    public function test_set_workspace_member_role_success_permissions_scope_ownership_and_retries(): void
    {
        $owner = $this->register('roles-owner@example.test');
        $project = $this->rpc('createProject', [
            'name' => '私有專案',
            'code' => 'PRIVATE',
        ])->assertOk()->json('data');
        $member = $this->join('roles-member@example.test');
        $this->getJson('/api/v1/workspace/settings')->assertOk()->assertJsonMissingPath('data.members');
        $this->getJson('/api/v1/workspace/members')->assertStatus(403);
        $this->rpc('updateWorkspace', [
            'name' => '越權',
            'expectedRevision' => 0,
        ])->assertStatus(403);
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => $member['user']['id'],
            'roleId' => 'manager',
            'expectedRevision' => 0,
        ])->assertStatus(403);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.projects');
        $this->actingAs(User::findOrFail($owner['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => $owner['user']['id'],
            'roleId' => 'user',
            'expectedRevision' => 0,
        ])->assertStatus(403);
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => $member['user']['id'],
            'roleId' => 'owner',
            'expectedRevision' => 0,
        ])->assertStatus(400);
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => 'not-in-workspace',
            'roleId' => 'manager',
            'expectedRevision' => 0,
        ])->assertStatus(404);
        $input = [
            'userId' => $member['user']['id'],
            'roleId' => 'manager',
            'expectedRevision' => 0,
            'requestId' => 'promote-member',
        ];
        $this->rpc('setWorkspaceMemberRole', $input)->assertOk()->assertJsonPath('data.revision', 1);
        $this->rpc('setWorkspaceMemberRole', $input)->assertOk()->assertJsonPath('data.revision', 1);
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => $member['user']['id'],
            'roleId' => 'user',
            'expectedRevision' => 0,
        ])->assertStatus(409);
        $this->assertDatabaseHas('workspace_members', [
            'user_id' => $member['user']['id'],
            'role' => 'manager',
            'role_revision' => 1,
        ]);
        $this->actingAs(User::findOrFail($member['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $this->rpc('getWorkspace')->assertJsonPath('data.projects.0.id', $project['id']);
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => $owner['user']['id'],
            'roleId' => 'user',
            'expectedRevision' => 0,
        ])->assertStatus(403);
        $rename = [
            'name' => '管理者改名',
            'expectedRevision' => 0,
            'requestId' => 'manager-rename',
        ];
        $this->rpc('updateWorkspace', $rename)->assertOk();
        $this->actingAs(User::findOrFail($owner['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => $member['user']['id'],
            'roleId' => 'user',
            'expectedRevision' => 1,
        ])->assertOk()->assertJsonPath('data.revision', 2);
        $this->actingAs(User::findOrFail($member['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $this->rpc('updateWorkspace', $rename)->assertStatus(403);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.projects');
        $this->assertDatabaseHas('projects', ['public_id' => $project['id']]);
    }

    /** 驗證版本欄升降不改設定或密碼。
     * @return void
     */
    public function test_settings_migration_success_preserves_values_and_passwords(): void
    {
        $owner = $this->register('migration-settings@example.test');
        $before = DB::table('users')->where('id', $owner['user']['id'])->first();
        $migration = require database_path('migrations/2026_11_05_000001_add_settings_revisions.php');
        $migration->down();
        $migration->up();
        $after = DB::table('users')->where('id', $owner['user']['id'])->first();
        $this->assertSame($before->password, $after->password);
        $this->assertSame($before->name, $after->name);
        $this->assertSame(0, $after->profile_revision);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }
}
