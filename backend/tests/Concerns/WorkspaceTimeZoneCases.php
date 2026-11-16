<?php

namespace Tests\Concerns;

use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** 驗證 UTC 預設、工作區顯示偏好與版本／租戶／授權, 兩個真實引擎共用 */
trait WorkspaceTimeZoneCases
{
    /** 工作區設定不改動 UTC 紀錄, 省略欄位及重播不重設時區
     * @return void
     */
    public function test_workspace_timezone_is_scoped_versioned_and_preserved_on_rename(): void
    {
        $owner = $this->postJson('/api/auth/register', ['name' => 'Zone owner', 'email' => Str::uuid().'@example.test', 'password' => 'timezone-test-password', 'workspaceName' => 'Zone'])->assertOk()->json('data');
        $this->assertSame('UTC', $owner['workspace']['timeZone']);
        $this->getJson('/api/v1/workspace/settings')->assertJsonPath('data.workspace.timeZone', 'UTC')->assertJsonMissingPath('data.members');
        $todo = $this->postJson('/api/rpc/createTodo', ['content' => 'Do not shift stored time', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $createdAt = $this->getJson('/api/v1/todos/'.$todo['id'])->assertOk()->json('data.createdAt');
        $this->assertStringEndsWith('Z', $createdAt);
        $input = ['name' => 'Zone', 'timeZone' => 'Asia/Taipei', 'expectedRevision' => 0, 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/updateWorkspace', $input)->assertOk()->assertJsonPath('data.revision', 1);
        $this->postJson('/api/rpc/updateWorkspace', $input)->assertOk()->assertJsonPath('data.revision', 1);
        $this->getJson('/api/v1/workspace/settings')->assertJsonPath('data.workspace.timeZone', 'Asia/Taipei');
        $this->postJson('/api/auth/current')->assertJsonPath('data.workspace.timeZone', 'Asia/Taipei');
        $this->getJson('/api/v1/todos/'.$todo['id'])->assertJsonPath('data.createdAt', $createdAt);
        foreach (['+08:00', 'Invalid/Timezone', 'Factory', '', null] as $zone) {
            $this->postJson('/api/rpc/updateWorkspace', ['name' => 'Must not save', 'timeZone' => $zone, 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertStatus(400);
        }
        $this->postJson('/api/rpc/updateWorkspace', ['name' => 'Renamed', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson('/api/v1/workspace/settings')->assertJsonPath('data.workspace.name', 'Renamed')->assertJsonPath('data.workspace.timeZone', 'Asia/Taipei')->assertJsonPath('data.workspace.revision', 2);
        $this->postJson('/api/rpc/updateWorkspace', ['name' => 'Stale', 'timeZone' => 'UTC', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertStatus(409);
        WorkspaceMember::where('user_id', $owner['user']['id'])->update(['role' => 'user']);
        $this->postJson('/api/rpc/updateWorkspace', ['name' => 'Denied', 'timeZone' => 'UTC', 'expectedRevision' => 2, 'requestId' => (string) Str::uuid()])->assertStatus(403);
        Auth::logout();
        $other = $this->postJson('/api/auth/register', ['name' => 'Other', 'email' => Str::uuid().'@example.test', 'password' => 'timezone-test-password', 'workspaceName' => 'Other'])->assertOk()->json('data');
        $this->postJson('/api/rpc/updateWorkspace', ['name' => 'Other', 'timeZone' => 'America/New_York', 'workspaceId' => $owner['workspace']['id'], 'expectedRevision' => 0, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->assertDatabaseHas('workspaces', ['public_id' => $owner['workspace']['id'], 'timezone' => 'Asia/Taipei']);
        $this->assertDatabaseHas('workspaces', ['public_id' => $other['workspace']['id'], 'timezone' => 'America/New_York']);
    }
}
