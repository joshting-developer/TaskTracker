<?php

namespace Tests\Feature;

use App\Events\ProjectChanged;
use App\Models\User;
use App\Repositories\ProjectReadRepository;
use App\Services\ProjectSignals;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** 驗證私有頻道、跨租戶／撤權及交易後無內容通知契約。 */
class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    /** 建立本測試自己的空間與帳號。
     * @param  string  $email  測試信箱
     * @return array
     */
    private function register(string $email): array
    {
        Auth::logout();

        return $this->postJson('/api/auth/register', [
            'name' => '即時測試',
            'email' => $email,
            'password' => 'realtime-test-password',
            'workspaceName' => '即時空間',
        ])->assertOk()->json('data');
    }

    /** 呼叫固定業務操作。
     * @param  string  $operation  已知操作
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

    /** 只在本測試設定假的公私鑰，沒有對外連線。
     * @return void
     */
    private function enable(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-public-key',
            'broadcasting.connections.reverb.secret' => 'test-private-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::purge('reverb');
    }

    /** 已登入且有讀取資格才取得頻道簽章；不洩漏私鑰。
     * @return void
     */
    public function test_authorize_channel_success_scope_permissions_and_public_config(): void
    {
        $this->call('POST', '/api/realtime/config', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}')->assertStatus(401);
        $owner = $this->register('realtime-owner@example.test');
        $project = $this->rpc('createProject', [
            'name' => '私有看板',
            'code' => 'LIVE',
        ])->assertOk()->json('data');
        $channel = 'private-workspaces.'.$owner['workspace']['id'].'.projects.'.$project['id'];
        $this->enable();
        $this->call('POST', '/api/realtime/config', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}')->assertOk()->assertExactJson([
            'ok' => true,
            'data' => [
                'enabled' => true,
                'key' => 'test-public-key',
                'path' => '/reverb',
            ],
        ]);
        $input = [
            'channel_name' => $channel,
            'socket_id' => '123.456',
        ];
        $expected = 'test-public-key:'.hash_hmac('sha256', '123.456:'.$channel, 'test-private-secret');
        $this->postJson('/api/realtime/authorize', $input)->assertOk()->assertJsonPath('data.auth', $expected)->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/realtime/authorize', [
            ...$input,
            'channel_name' => 'project.public',
        ])->assertStatus(400);
        $this->postJson('/api/realtime/authorize', [
            ...$input,
            'socket_id' => 'bad',
        ])->assertStatus(400);
        $this->postJson('/api/realtime/authorize', $input, ['Origin' => 'https://other.invalid'])->assertStatus(403);
        config(['broadcasting.default' => 'null']);
        $invite = $this->rpc('inviteMember', ['email' => 'realtime-reader@example.test'])->assertOk()->json('data');
        Auth::logout();
        $member = $this->postJson('/api/auth/register', [
            'name' => '讀者',
            'email' => 'realtime-reader@example.test',
            'password' => 'realtime-test-password',
            'inviteToken' => $invite['token'],
        ])->assertOk()->json('data');
        $this->enable();
        $this->postJson('/api/realtime/authorize', $input)->assertStatus(404);
        $this->actingAs(User::findOrFail($owner['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        config(['broadcasting.default' => 'null']);
        $this->rpc('setProjectMember', [
            'projectId' => $project['id'],
            'userId' => $member['user']['id'],
            'roleId' => 'reader',
            'expectedRevision' => 0,
        ])->assertOk();
        $this->actingAs(User::findOrFail($member['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $this->enable();
        $this->postJson('/api/realtime/authorize', $input)->assertOk();
        $this->actingAs(User::findOrFail($owner['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        config(['broadcasting.default' => 'null']);
        $this->rpc('removeProjectMember', [
            'projectId' => $project['id'],
            'userId' => $member['user']['id'],
            'expectedRevision' => 1,
        ])->assertOk();
        $this->actingAs(User::findOrFail($member['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $this->enable();
        $this->postJson('/api/realtime/authorize', $input)->assertStatus(404);
        config(['broadcasting.default' => 'null']);
        $this->register('realtime-foreign@example.test');
        $this->enable();
        $this->postJson('/api/realtime/authorize', $input)->assertStatus(403);
        $event = new ProjectChanged($owner['workspace']['id'], $project['id']);
        $this->assertSame(['changed' => true], $event->broadcastWith());
        $this->assertSame($channel, $event->broadcastOn()[0]->name);
    }

    /** commit前不通知、rollback不通知，HTTP重試不重複派送。
     * @return void
     */
    public function test_project_signals_success_commit_rollback_and_replay(): void
    {
        $context = $this->register('realtime-event@example.test');
        $project = $this->rpc('createProject', [
            'name' => '事件看板',
            'code' => 'EVENT',
        ])->assertOk()->json('data');
        $this->enable();
        Event::fake([ProjectChanged::class]);
        DB::beginTransaction();
        (new ProjectSignals($context, app(ProjectReadRepository::class)))->changed($project['id']);
        Event::assertNotDispatched(ProjectChanged::class);
        DB::rollBack();
        Event::assertNotDispatched(ProjectChanged::class);
        DB::beginTransaction();
        (new ProjectSignals($context, app(ProjectReadRepository::class)))->changed($project['id']);
        Event::assertNotDispatched(ProjectChanged::class);
        DB::commit();
        Event::assertDispatchedTimes(ProjectChanged::class, 1);
        Event::fake([ProjectChanged::class]);
        $input = [
            'projectId' => $project['id'],
            'title' => '即時任務',
            'requestId' => 'unique-live-write',
        ];
        $issue = $this->rpc('createIssue', $input)->assertOk()->json('data');
        Event::assertDispatchedTimes(ProjectChanged::class, 1);
        $this->rpc('createIssue', $input)->assertOk();
        Event::assertDispatchedTimes(ProjectChanged::class, 1);
        $this->rpc('updateIssue', [
            'id' => $issue['id'],
            'expectedRevision' => 0,
            'title' => '衝突',
        ])->assertStatus(409);
        Event::assertDispatchedTimes(ProjectChanged::class, 1);
    }

    /**
     * commit後派送失敗不能改成API失敗或遺失已保存的任務。
     *
     * @return void
     */
    public function test_project_signals_queue_failure_preserves_successful_write(): void
    {
        $this->register('realtime-failure@example.test');
        $project = $this->rpc('createProject', [
            'name' => '派送失敗',
            'code' => 'FAIL',
        ])->assertOk()->json('data');
        $this->enable();
        Event::partialMock()->shouldReceive('dispatch')->with(\Mockery::type(ProjectChanged::class))->once()->andThrow(new \RuntimeException('synthetic queue failure'));
        Log::spy();
        $issue = $this->rpc('createIssue', [
            'projectId' => $project['id'],
            'title' => '仍需保存',
        ])->assertOk()->json('data');
        $this->rpc('getWorkspace')->assertJsonFragment([
            'id' => $issue['id'],
            'title' => '仍需保存',
        ]);
        Log::shouldHaveReceived('warning')->once()->with('Project realtime dispatch failed; polling remains available', ['exception' => \RuntimeException::class]);
    }

    /** 正式停廣播時, 寫卡不留下無人消費的Queue Job
     * @return void
     */
    public function test_null_broadcast_never_queues_a_project_change(): void
    {
        config(['broadcasting.default' => 'null', 'delivery.mode' => 'inline', 'mail.default' => 'array']);
        Queue::fake();
        $this->register('null-broadcast-'.Str::uuid().'@example.test');
        $project = $this->rpc('createProject', ['name' => 'No socket', 'code' => 'NOSOCKET'])->assertOk()->json('data');
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'No background signal'])->assertOk();
        Queue::assertNotPushed(BroadcastEvent::class);
    }
}
