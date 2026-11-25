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

    /** 尺寸偏好保存與邊界驗證。 */
    public function test_widget_dimensions_round_trip(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Resize', 'email' => 'resize@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Resize'])->assertOk();
        $widget = ['id' => 'mine', 'kind' => 'mine', 'width' => 2, 'height' => 700, 'title' => '高優先', 'filters' => ['priority' => 'high', 'assignee' => 'all']];
        $this->putJson(self::URL, ['widgets' => [$widget], 'expectedRevision' => 0])->assertOk()->assertJsonPath('data.widgets.0.width', 2);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.widgets.0.height', 700);
        $this->putJson(self::URL, ['widgets' => [array_merge($widget, ['width' => 4])], 'expectedRevision' => 1])->assertStatus(400);
        $this->putJson(self::URL, ['widgets' => [$widget, $widget], 'expectedRevision' => 1])->assertStatus(400);
        $this->putJson(self::URL, ['widgets' => [array_merge($widget, ['height' => 800])], 'expectedRevision' => 0])->assertStatus(409);
    }

    /** 新欄位與小尺寸可保存, 舊設定不被重寫。 */
    public function test_twelve_column_layout_preserves_legacy_widgets(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Columns', 'email' => 'columns@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Columns'])->assertOk();
        $widget = ['id' => 'new-widget', 'kind' => 'tasks', 'width' => 1, 'columns' => 7, 'height' => 200];
        $this->putJson(self::URL, ['widgets' => ['mine', $widget], 'expectedRevision' => 0])->assertOk()->assertJsonPath('data.widgets.0', 'mine')->assertJsonPath('data.widgets.1.columns', 7);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.widgets.1.height', 200);
        foreach ([['columns' => 13], ['columns' => 2], ['height' => 179], ['filters' => ['actorId' => 'other']]] as $bad) {
            $this->putJson(self::URL, ['widgets' => [array_merge($widget, $bad)], 'expectedRevision' => 1])->assertStatus(400);
        }
    }

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
        $this->assertSame(['mine', 'due', 'starting', 'recent', 'notes', 'tasks'], $saved['available'], '隱藏的仍列在可加入');
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
