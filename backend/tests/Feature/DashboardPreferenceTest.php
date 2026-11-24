<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** TT-186：首頁小工具偏好以成員保存, 白名單、版本衝突與同內容重送冪等 */
class DashboardPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /** 偏好端點 */
    private const URL = '/api/v1/personal-dashboard/preferences';

    /** 預設、保存、重送、衝突、白名單與成員隔離
     * @return void
     */
    public function test_member_preferences_round_trip_with_revision_guard(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'dash-owner@example.test', 'password' => 'test-local-password', 'workspaceName' => '首頁'])->assertOk();
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.widgets', ['mine', 'due', 'starting', 'recent', 'notes'])->assertJsonPath('data.revision', 0);

        $saved = $this->putJson(self::URL, ['widgets' => ['due', 'notes', 'mine'], 'expectedRevision' => 0])->assertOk()->json('data');
        $this->assertSame(['due', 'notes', 'mine'], $saved['widgets']);
        $this->assertSame(1, $saved['revision']);
        $this->assertSame(['mine', 'due', 'starting', 'recent', 'notes'], $saved['available'], '隱藏的仍列在可加入');
        $this->putJson(self::URL, ['widgets' => ['due', 'notes', 'mine'], 'expectedRevision' => 0])->assertOk()->assertJsonPath('data.revision', 1);
        $this->putJson(self::URL, ['widgets' => ['recent'], 'expectedRevision' => 0])->assertStatus(409);
        $this->putJson(self::URL, ['widgets' => [], 'expectedRevision' => 1])->assertOk()->assertJsonPath('data.widgets', [])->assertJsonPath('data.revision', 2);

        foreach ([['widgets' => ['time'], 'expectedRevision' => 2], ['widgets' => ['mine', 'mine'], 'expectedRevision' => 2], ['widgets' => ['mine']], ['widgets' => ['mine'], 'expectedRevision' => 2, 'extra' => 1]] as $invalid) {
            $this->putJson(self::URL, $invalid)->assertStatus(400);
        }

        $user = User::create(['id' => (string) Str::uuid(), 'name' => 'Alice', 'email' => 'dash-alice@example.test', 'password' => Hash::make('test-local-password')]);
        (new RecordStore)->forWorkspace(session('workspace_id'))->put('members', $user->id, ['id' => $user->id, 'name' => 'Alice', 'email' => $user->email, 'role' => 'user']);
        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', ['email' => 'dash-alice@example.test', 'password' => 'test-local-password'])->assertOk();
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.revision', 0)->assertJsonPath('data.widgets.0', 'mine');
    }
}
