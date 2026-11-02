<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TodoTest extends TestCase
{
    use RefreshDatabase;
    private array $owner;
    private array $alice;
    private array $bob;
    private RecordStore $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'todo-owner@example.test', 'password' => 'test-local-password', 'workspaceName' => '待辦空間'])->assertOk()->json('data.user');
        $workspaceId = session('workspace_id');
        $this->tenant = (new RecordStore)->forWorkspace($workspaceId);
        $this->alice = $this->member('Alice'); $this->bob = $this->member('Bob');
    }
    private function member(string $name, string $role = 'user'): array
    {
        $user = User::create(['id' => (string) Str::uuid(), 'name' => $name, 'email' => strtolower($name).'@example.test', 'password' => Hash::make('test-local-password')]);
        $value = ['id' => $user->id, 'name' => $name, 'email' => $user->email, 'role' => $role];
        $this->tenant->put('members', $user->id, $value);
        return $value;
    }
    private function login(array $member): void { Auth::logout(); $this->postJson('/api/auth/login', ['email' => $member['email'], 'password' => 'test-local-password'])->assertOk(); }
    private function rpc(string $op, array $input = []) { return $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input]); }
    private function todo(string $title = '小事', ?string $to = null): array { return $this->rpc('createTodo', ['title' => $title, ...($to ? ['assigneeId' => $to] : [])])->assertOk()->json('data'); }

    public function test_self_notes_need_no_project_and_protect_versions_identity_and_state(): void
    {
        $this->login($this->alice);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.projects');
        $created = $this->rpc('createTodo', ['title' => '  準備快照  ', 'note' => '<script>純文字</script>', 'createdBy' => $this->bob['id'], 'state' => 'done', 'requestId' => 'create-self'])->assertOk()->json('data');
        $this->assertSame(['id', 'revision'], array_keys($created));
        $item = $this->rpc('getTodos')->assertJsonCount(1, 'data.todos')->assertJsonCount(0, 'data.notifications')->json('data.todos.0');
        $this->assertSame($this->alice['id'], $item['createdBy']); $this->assertSame('open', $item['state']);
        $this->assertSame('準備快照', $item['title']); $this->assertTrue($item['capabilities']['edit']);
        $this->rpc('updateTodo', ['id' => $item['id'], 'expectedRevision' => 0, 'title' => '衝突'])->assertStatus(409);
        $this->rpc('updateTodo', ['id' => $item['id'], 'expectedRevision' => 1, 'title' => '補上測試'])->assertOk()->assertJsonPath('data.revision', 2);
        $complete = ['id' => $item['id'], 'expectedRevision' => 2, 'requestId' => 'complete-self'];
        $this->rpc('completeTodo', $complete)->assertOk(); $this->rpc('completeTodo', $complete)->assertOk();
        $this->assertSame(3, $this->tenant->require('todos', $item['id'])['revision']);
        $this->rpc('updateTodo', ['id' => $item['id'], 'expectedRevision' => 3, 'title' => '不能更動已完成'])->assertStatus(400);
        $this->rpc('reopenTodo', ['id' => $item['id'], 'expectedRevision' => 3])->assertOk();
        $this->rpc('cancelTodo', ['id' => $item['id'], 'expectedRevision' => 4])->assertOk();
        $this->rpc('completeTodo', ['id' => $item['id'], 'expectedRevision' => 5])->assertStatus(400);
        $this->rpc('returnTodo', ['id' => $item['id'], 'expectedRevision' => 5])->assertStatus(403);
        foreach ([['title' => ''], ['title' => str_repeat('字', 181)], ['title' => 'test', 'note' => []], ['title' => 'test', 'assigneeId' => 'unknown']] as $invalid) $this->rpc('createTodo', $invalid)->assertStatus(400);
    }

    public function test_any_member_can_assign_upwards_return_and_notify_once_without_acceptance(): void
    {
        $this->login($this->alice);
        $payload = ['title' => '幫忙確認', 'assigneeId' => $this->owner['id'], 'requestId' => 'upward'];
        $created = $this->rpc('createTodo', $payload)->assertOk()->json('data'); $this->rpc('createTodo', $payload)->assertOk();
        $this->assertCount(1, $this->tenant->all('todos')); $this->assertCount(1, $this->tenant->all('todoNotifications'));
        $this->rpc('completeTodo', ['id' => $created['id'], 'expectedRevision' => 1])->assertStatus(403);
        $this->login($this->owner);
        $data = $this->rpc('getTodos')->assertJsonPath('data.todos.0.state', 'open')->assertJsonCount(1, 'data.notifications')->json('data');
        $notification = $data['notifications'][0];
        $this->rpc('readTodoNotification', ['id' => $notification['id'], 'requestId' => 'read'])->assertOk();
        $this->rpc('readTodoNotification', ['id' => $notification['id'], 'requestId' => 'read'])->assertOk();
        $this->assertNotNull($this->tenant->require('todoNotifications', $notification['id'])['readAt']);
        $this->rpc('updateTodo', ['id' => $created['id'], 'expectedRevision' => 1, 'title' => '承辦者不可改需求'])->assertStatus(403);
        $this->rpc('returnTodo', ['id' => $created['id'], 'expectedRevision' => 1, 'requestId' => 'return-upward'])->assertOk();
        $this->rpc('returnTodo', ['id' => $created['id'], 'expectedRevision' => 1, 'requestId' => 'return-upward'])->assertStatus(404);
        $this->rpc('getTodos')->assertJsonCount(0, 'data.todos')->assertJsonCount(0, 'data.notifications');
        $this->login($this->alice);
        $this->rpc('getTodos')->assertJsonPath('data.todos.0.assigneeId', $this->alice['id'])->assertJsonPath('data.notifications.0.event', 'returned');
        $this->assertCount(2, $this->tenant->all('todoNotifications'));
        $this->rpc('completeTodo', ['id' => $created['id'], 'expectedRevision' => 2])->assertOk();
    }

    public function test_third_party_even_owner_and_other_tenants_cannot_read_write_or_mark_notifications(): void
    {
        $this->login($this->alice); $created = $this->todo('私人文字', $this->bob['id']);
        $notification = $this->tenant->all('todoNotifications')[0];
        $this->login($this->owner);
        $this->rpc('getTodos')->assertJsonCount(0, 'data.todos')->assertJsonCount(0, 'data.notifications');
        foreach (['updateTodo', 'completeTodo', 'reopenTodo', 'returnTodo', 'cancelTodo', 'convertTodo'] as $op) $this->rpc($op, ['id' => $created['id'], 'expectedRevision' => 1])->assertStatus(404);
        $this->rpc('readTodoNotification', ['id' => $notification['id']])->assertStatus(404);
        Auth::logout();
        $outsider = $this->postJson('/api/auth/register', ['name' => 'Outside', 'email' => 'outside@example.test', 'password' => 'test-local-password', 'workspaceName' => '另一空間'])->assertOk()->json('data.user');
        $this->rpc('getTodos')->assertJsonCount(0, 'data.todos')->assertJsonCount(1, 'data.members');
        $this->rpc('updateTodo', ['id' => $created['id'], 'expectedRevision' => 1])->assertStatus(404);
        $this->rpc('createTodo', ['title' => '跨空間', 'assigneeId' => $this->alice['id']])->assertStatus(400);
        $this->login($this->alice);
        $this->rpc('createTodo', ['title' => '反向跨空間', 'assigneeId' => $outsider['id']])->assertStatus(400);
        $this->rpc('readTodoNotification', ['id' => $notification['id']])->assertStatus(404);
    }

    public function test_completion_reopen_cancel_have_author_limits_and_notification_history(): void
    {
        $this->login($this->alice); $created = $this->todo('幫我補測試', $this->bob['id']);
        $this->rpc('updateTodo', ['id' => $created['id'], 'expectedRevision' => 1, 'note' => '補充'])->assertOk();
        $this->login($this->bob);
        $this->rpc('cancelTodo', ['id' => $created['id'], 'expectedRevision' => 2])->assertStatus(403);
        $payload = ['id' => $created['id'], 'expectedRevision' => 2, 'requestId' => 'done-other'];
        $this->rpc('completeTodo', $payload)->assertOk(); $this->rpc('completeTodo', $payload)->assertOk();
        $this->login($this->alice);
        $this->rpc('getTodos')->assertJsonPath('data.todos.0.state', 'done')->assertJsonPath('data.notifications.0.event', 'completed');
        $this->rpc('reopenTodo', ['id' => $created['id'], 'expectedRevision' => 3])->assertStatus(403);
        $this->login($this->bob); $this->rpc('reopenTodo', ['id' => $created['id'], 'expectedRevision' => 3])->assertOk();
        $this->login($this->alice); $this->rpc('cancelTodo', ['id' => $created['id'], 'expectedRevision' => 4])->assertOk();
        $this->assertSame(['created','edited','completed','reopened','canceled'], array_column($this->tenant->require('todos', $created['id'])['history'], 'event'));
        $this->assertCount(5, $this->tenant->all('todoNotifications'));
        $this->rpc('createTodo', ['title' => '不同內容', 'requestId' => 'done-other'])->assertOk(); // requestId 按操作者隔離
    }

    public function test_conversion_is_atomic_one_way_idempotent_and_project_scoped(): void
    {
        $project = $this->rpc('createProject', ['name' => '隱藏 Project', 'code' => 'CONVERT'])->assertOk()->json('data');
        $this->login($this->alice); $created = $this->todo('從小事變大事', $this->owner['id']);
        $this->login($this->owner);
        $bad = ['id' => $created['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id'], 'title' => '卡片', 'assignee' => $this->alice['id']]];
        $this->rpc('convertTodo', $bad)->assertStatus(400);
        $this->assertCount(0, $this->tenant->all('issues')); $this->assertSame('open', $this->tenant->require('todos', $created['id'])['state']);
        $payload = ['id' => $created['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id'], 'title' => '卡片補齊', 'type' => 'bug'], 'requestId' => 'convert-once'];
        $this->rpc('convertTodo', $payload)->assertOk(); $this->rpc('convertTodo', $payload)->assertOk();
        $this->assertCount(1, $this->tenant->all('issues')); $this->assertCount(2, $this->tenant->all('todoNotifications'));
        $this->rpc('getTodos')->assertJsonPath('data.todos.0.state', 'converted')->assertJsonPath('data.todos.0.convertedIssue.identifier', 'CONVERT-1');
        $this->rpc('convertTodo', [...$payload, 'requestId' => 'different', 'expectedRevision' => 2])->assertStatus(400);
        $this->rpc('reopenTodo', ['id' => $created['id'], 'expectedRevision' => 2])->assertStatus(400);
        $this->login($this->alice);
        $visible = $this->rpc('getTodos')->assertJsonPath('data.todos.0.convertedIssue', null)->json('data');
        $this->assertStringNotContainsString($project['id'], json_encode($visible));
        $this->assertStringNotContainsString('CONVERT-1', json_encode($visible));
        $self = $this->todo('沒有 Project 寫入資格');
        $this->rpc('convertTodo', ['id' => $self['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id'], 'title' => '拒絕']])->assertStatus(404);
        $this->rpc('createTodoFromIssue', ['issueId' => $this->tenant->all('issues')[0]['id']])->assertStatus(404);
    }

    public function test_disabled_members_and_revoked_project_permissions_cannot_replay_requests(): void
    {
        $project = $this->rpc('createProject', ['name' => '可撤權', 'code' => 'REVOKE'])->assertOk()->json('data');
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $this->alice['id'], 'roleId' => 'developer', 'expectedRevision' => 0])->assertOk();
        $this->login($this->alice); $todo = $this->todo();
        $payload = ['id' => $todo['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id'], 'title' => '正式卡'], 'requestId' => 'revoked'];
        $this->rpc('convertTodo', $payload)->assertOk();
        $this->login($this->owner); $this->rpc('removeProjectMember', ['projectId' => $project['id'], 'userId' => $this->alice['id'], 'expectedRevision' => 1])->assertOk();
        $this->login($this->alice); $this->rpc('convertTodo', $payload)->assertStatus(404);
        $this->tenant->put('members', $this->alice['id'], [...$this->alice, 'disabledAt' => now()->toISOString()]);
        $this->rpc('createTodo', ['title' => '失效'])->assertStatus(401);
        $this->rpc('getTodos')->assertStatus(401);
    }
}
