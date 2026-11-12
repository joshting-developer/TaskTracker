<?php

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** 驗證私人待辦、跨角色交辦、退回通知與單向轉卡的權限及版本。 */
class TodoTest extends TestCase
{
    use RefreshDatabase;

    private array $owner;

    private array $alice;

    private array $bob;

    private RecordStore $tenant;

    /**
     * 建立獨立待辦Workspace、擁有者與Alice／Bob測試成員。
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'todo-owner@example.test', 'password' => 'test-local-password', 'workspaceName' => '待辦空間'])->assertOk()->json('data.user');
        $workspaceId = session('workspace_id');
        $this->tenant = (new RecordStore)->forWorkspace($workspaceId);
        $this->alice = $this->member('Alice');
        $this->bob = $this->member('Bob');
    }

    /**
     * 在目前測試Workspace建立指定角色的帳號與有效成員紀錄。
     *
     * @param  string  $name  測試帳號名稱
     * @param  string  $role  工作區角色映射，預設一般成員
     * @return array{id:string,name:string,email:string,role:string} 測試成員
     */
    private function member(string $name, string $role = 'user'): array
    {
        $user = User::create(['id' => (string) Str::uuid(), 'name' => $name, 'email' => strtolower($name).'@example.test', 'password' => Hash::make('test-local-password')]);
        $value = ['id' => $user->id, 'name' => $name, 'email' => $user->email, 'role' => $role];
        $this->tenant->put('members', $user->id, $value);

        return $value;
    }

    /**
     * 透過正式登入入口切換至指定測試成員，不沿用上一位操作者的Auth。
     *
     * @param  array  $member  含email的測試帳號或成員資料
     * @return void
     */
    private function login(array $member): void
    {
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $member['email'], 'password' => 'test-local-password'])->assertOk();
    }

    /**
     * 經正式Session RPC呼叫指定操作，為測試輸入補入可覆寫的requestId。
     *
     * @param  string  $op  已註冊的RPC操作
     * @param  array<string,mixed>  $input  測試輸入，可明確指定原requestId
     * @return TestResponse 真實HTTP測試回應
     */
    private function rpc(string $op, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 為目前操作者建立待辦，可指定承辦者並回有界寫入結果。
     *
     * @param  string  $title  測試待辦標題
     * @param  ?string  $to  承辦者ID；null時由自己承辦
     * @return array{id:string,revision:int} 待辦寫入結果
     */
    private function todo(string $title = '小事', ?string $to = null): array
    {
        return $this->rpc('createTodo', ['title' => $title, ...($to ? ['assigneeId' => $to] : [])])->assertOk()->json('data');
    }

    /** 完整速記獨立保存，摘要不截斷原稿，沿用權限／版本／重試。
     * @return void
     */
    public function test_markdown_content_preserves_source_and_existing_write_guards(): void
    {
        $this->login($this->alice);
        $content = '# '.str_repeat('原稿', 120)."\n\n- [ ] 補測試\n- [x] 準備快照\n\n```php\necho 1;\n```\n保留換行  \n下一行\n";
        $payload = ['content' => $content, 'requestId' => 'markdown-create'];
        $created = $this->rpc('createTodo', $payload)->assertOk()->json('data');
        $this->rpc('createTodo', $payload)->assertJsonPath('data.id', $created['id']);
        $item = $this->rpc('getTodos')->assertJsonCount(1, 'data.todos')->json('data.todos.0');
        $this->assertSame($content, $item['content']);
        $this->assertSame(180, mb_strlen($item['title']));
        $this->assertSame('', $item['note']);
        $this->assertDatabaseHas('todos', ['public_id' => $item['id'], 'content' => $content]);
        $update = ['id' => $item['id'], 'expectedRevision' => 1, 'content' => "## 新內容\n\n**已確認**", 'requestId' => 'markdown-edit'];
        $this->rpc('updateTodo', $update)->assertJsonPath('data.revision', 2);
        $this->rpc('updateTodo', $update)->assertJsonPath('data.revision', 2);
        $this->rpc('updateTodo', [...$update, 'requestId' => 'markdown-stale'])->assertStatus(409);
        $this->assertSame('新內容', $this->tenant->require('todos', $item['id'])['title']);
        $this->rpc('updateTodo', ['id' => $item['id'], 'expectedRevision' => 2, 'title' => '僅改摘要'])->assertOk();
        $this->assertSame($update['content'], $this->tenant->require('todos', $item['id'])['content']);
        foreach (['', " \n ", null, [], str_repeat('字', 6001)] as $invalid) {
            $this->rpc('createTodo', ['content' => $invalid])->assertStatus(400);
        }
        $this->login($this->bob);
        $this->rpc('getTodos')->assertJsonCount(0, 'data.todos');
        $this->rpc('updateTodo', ['id' => $item['id'], 'expectedRevision' => 2, 'content' => '越權'])->assertStatus(404);
    }

    /** 轉卡未另外改寫描述時，預設帶完整Markdown而非摘要。
     * @return void
     */
    public function test_markdown_conversion_defaults_to_full_source(): void
    {
        $this->login($this->owner);
        $content = "# 交付檢查\n\n- [ ] 手機驗收\n\n    echo '保留縮排';\n";
        $todo = $this->rpc('createTodo', ['content' => $content])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => '驗收', 'code' => 'MD'])->assertOk()->json('data');
        $this->rpc('convertTodo', ['id' => $todo['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id']]])->assertOk();
        $this->assertSame($content, $this->tenant->all('issues')[0]['description']);
        $this->assertSame($content, $this->tenant->require('todos', $todo['id'])['content']);
    }

    /** 舊資料不改，欄位回退及整體儲存往返都保留新原稿。
     * @return void
     */
    public function test_markdown_migration_round_trip_preserves_legacy_and_new_content(): void
    {
        $this->login($this->alice);
        $old = $this->rpc('createTodo', ['title' => '舊標題', 'note' => '舊補充'])->assertOk()->json('data');
        $new = $this->rpc('createTodo', ['content' => "# 新速記\n\n- [ ] 一起確認"])->assertOk()->json('data');
        $before = [$this->tenant->require('todos', $old['id']), $this->tenant->require('todos', $new['id'])];
        $markdown = require database_path('migrations/2026_11_05_000000_add_todo_markdown_content.php');
        $relational = require database_path('migrations/2026_11_04_000000_create_relational_business_tables.php');
        $markdown->down();
        Schema::dropIfExists('issue_external_refs'); // 後續migration的FK表；正常rollback會先撤它
        $relational->down();
        $relational->up();
        $markdown->up();
        $this->assertSame($before, [$this->tenant->require('todos', $old['id']), $this->tenant->require('todos', $new['id'])]);
        $this->assertSame([], json_decode(DB::table('todos')->where('public_id', $new['id'])->value('legacy_attributes'), true));
    }

    /**
     * 驗證自記待辦不必屬於專案，且身分、revision與狀態操作仍受保護。
     *
     * @return void
     */
    public function test_self_notes_need_no_project_and_protect_versions_identity_and_state(): void
    {
        $this->login($this->alice);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.projects');
        $created = $this->rpc('createTodo', ['title' => '  準備快照  ', 'note' => '<script>純文字</script>', 'createdBy' => $this->bob['id'], 'state' => 'done', 'requestId' => 'create-self'])->assertOk()->json('data');
        $this->assertSame(['id', 'revision'], array_keys($created));
        $item = $this->rpc('getTodos')->assertJsonCount(1, 'data.todos')->assertJsonCount(0, 'data.notifications')->json('data.todos.0');
        $this->assertSame($this->alice['id'], $item['createdBy']);
        $this->assertSame('open', $item['state']);
        $this->assertSame('準備快照', $item['title']);
        $this->assertTrue($item['capabilities']['edit']);
        $this->rpc('updateTodo', ['id' => $item['id'], 'expectedRevision' => 0, 'title' => '衝突'])->assertStatus(409);
        $this->rpc('updateTodo', ['id' => $item['id'], 'expectedRevision' => 1, 'title' => '補上測試'])->assertOk()->assertJsonPath('data.revision', 2);
        $complete = ['id' => $item['id'], 'expectedRevision' => 2, 'requestId' => 'complete-self'];
        $this->rpc('completeTodo', $complete)->assertOk();
        $this->rpc('completeTodo', $complete)->assertOk();
        $this->assertSame(3, $this->tenant->require('todos', $item['id'])['revision']);
        $this->rpc('updateTodo', ['id' => $item['id'], 'expectedRevision' => 3, 'title' => '不能更動已完成'])->assertStatus(400);
        $this->rpc('reopenTodo', ['id' => $item['id'], 'expectedRevision' => 3])->assertOk();
        $this->rpc('cancelTodo', ['id' => $item['id'], 'expectedRevision' => 4])->assertOk();
        $this->rpc('completeTodo', ['id' => $item['id'], 'expectedRevision' => 5])->assertStatus(400);
        $this->rpc('returnTodo', ['id' => $item['id'], 'expectedRevision' => 5])->assertStatus(403);
        foreach ([['title' => ''], ['title' => str_repeat('字', 181)], ['title' => 'test', 'note' => []], ['title' => 'test', 'assigneeId' => 'unknown']] as $invalid) {
            $this->rpc('createTodo', $invalid)->assertStatus(400);
        }
    }

    /**
     * 驗證成員可直接交辦給管理者或擁有者、承辦人可退回，重試不重複通知。
     *
     * @return void
     */
    public function test_any_member_can_assign_upwards_return_and_notify_once_without_acceptance(): void
    {
        $this->login($this->alice);
        $payload = ['title' => '幫忙確認', 'assigneeId' => $this->owner['id'], 'requestId' => 'upward'];
        $created = $this->rpc('createTodo', $payload)->assertOk()->json('data');
        $this->rpc('createTodo', $payload)->assertOk();
        $this->assertCount(1, $this->tenant->all('todos'));
        $this->assertCount(1, $this->tenant->all('todoNotifications'));
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

    /**
     * 驗證非交辦／承辦者即使是Owner，也不能讀寫他人待辦或標記其通知；跨租戶同樣拒絕。
     *
     * @return void
     */
    public function test_third_party_even_owner_and_other_tenants_cannot_read_write_or_mark_notifications(): void
    {
        $this->login($this->alice);
        $created = $this->todo('私人文字', $this->bob['id']);
        $notification = $this->tenant->all('todoNotifications')[0];
        $this->login($this->owner);
        $this->rpc('getTodos')->assertJsonCount(0, 'data.todos')->assertJsonCount(0, 'data.notifications');
        foreach (['updateTodo', 'completeTodo', 'reopenTodo', 'returnTodo', 'cancelTodo', 'convertTodo'] as $op) {
            $this->rpc($op, ['id' => $created['id'], 'expectedRevision' => 1])->assertStatus(404);
        }
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

    /**
     * 驗證待辦完成、重開與取消的作者限制，以及通知與歷史保存。
     *
     * @return void
     */
    public function test_completion_reopen_cancel_have_author_limits_and_notification_history(): void
    {
        $this->login($this->alice);
        $created = $this->todo('幫我補測試', $this->bob['id']);
        $this->rpc('updateTodo', ['id' => $created['id'], 'expectedRevision' => 1, 'note' => '補充'])->assertOk();
        $this->login($this->bob);
        $this->rpc('cancelTodo', ['id' => $created['id'], 'expectedRevision' => 2])->assertStatus(403);
        $payload = ['id' => $created['id'], 'expectedRevision' => 2, 'requestId' => 'done-other'];
        $this->rpc('completeTodo', $payload)->assertOk();
        $this->rpc('completeTodo', $payload)->assertOk();
        $this->login($this->alice);
        $this->rpc('getTodos')->assertJsonPath('data.todos.0.state', 'done')->assertJsonPath('data.notifications.0.event', 'completed');
        $this->rpc('reopenTodo', ['id' => $created['id'], 'expectedRevision' => 3])->assertStatus(403);
        $this->login($this->bob);
        $this->rpc('reopenTodo', ['id' => $created['id'], 'expectedRevision' => 3])->assertOk();
        $this->login($this->alice);
        $this->rpc('cancelTodo', ['id' => $created['id'], 'expectedRevision' => 4])->assertOk();
        $this->assertSame(['created', 'edited', 'completed', 'reopened', 'canceled'], array_column($this->tenant->require('todos', $created['id'])['history'], 'event'));
        $this->assertCount(5, $this->tenant->all('todoNotifications'));
        $this->rpc('createTodo', ['title' => '不同內容', 'requestId' => 'done-other'])->assertOk(); // requestId 按操作者隔離
    }

    /**
     * 驗證待辦轉卡單向、原子且可冪等重試，目標專案權限與卡片欄位同時生效。
     *
     * @return void
     */
    public function test_conversion_is_atomic_one_way_idempotent_and_project_scoped(): void
    {
        $project = $this->rpc('createProject', ['name' => '隱藏 Project', 'code' => 'CONVERT'])->assertOk()->json('data');
        $this->login($this->alice);
        $created = $this->todo('從小事變大事', $this->owner['id']);
        $this->login($this->owner);
        $bad = ['id' => $created['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id'], 'title' => '卡片', 'assignee' => $this->alice['id']]];
        $this->rpc('convertTodo', $bad)->assertStatus(400);
        $this->assertCount(0, $this->tenant->all('issues'));
        $this->assertSame('open', $this->tenant->require('todos', $created['id'])['state']);
        $payload = ['id' => $created['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id'], 'title' => '卡片補齊', 'type' => 'bug'], 'requestId' => 'convert-once'];
        $this->rpc('convertTodo', $payload)->assertOk();
        $this->rpc('convertTodo', $payload)->assertOk();
        $this->assertCount(1, $this->tenant->all('issues'));
        $this->assertCount(2, $this->tenant->all('todoNotifications'));
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

    /**
     * 驗證停用成員或撤銷專案權限後，既有待辦請求也不能藉重播繞過授權。
     *
     * @return void
     */
    public function test_disabled_members_and_revoked_project_permissions_cannot_replay_requests(): void
    {
        $project = $this->rpc('createProject', ['name' => '可撤權', 'code' => 'REVOKE'])->assertOk()->json('data');
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $this->alice['id'], 'roleId' => 'developer', 'expectedRevision' => 0])->assertOk();
        $this->login($this->alice);
        $todo = $this->todo();
        $payload = ['id' => $todo['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id'], 'title' => '正式卡'], 'requestId' => 'revoked'];
        $this->rpc('convertTodo', $payload)->assertOk();
        $this->login($this->owner);
        $this->rpc('removeProjectMember', ['projectId' => $project['id'], 'userId' => $this->alice['id'], 'expectedRevision' => 1])->assertOk();
        $this->login($this->alice);
        $this->rpc('convertTodo', $payload)->assertStatus(404);
        $this->tenant->put('members', $this->alice['id'], [...$this->alice, 'disabledAt' => now()->toISOString()]);
        $this->rpc('createTodo', ['title' => '失效'])->assertStatus(401);
        $this->rpc('getTodos')->assertStatus(401);
    }

    /**
     * 個人查詢先在SQL分頁及限參與者，摘要不讀原稿／歷史，通知獨立讀取。
     *
     * @return void
     */
    public function test_scoped_personal_reads_bound_content_history_notifications_and_return_access(): void
    {
        $this->login($this->alice);
        $ids = [];
        for ($i = 0; $i < 25; $i++) {
            $ids[] = $this->rpc('createTodo', ['content' => 'Note '.$i."\n".str_repeat('private-content ', 200)])->assertOk()->json('data.id');
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = $this->getJson('/api/v1/todos')->assertOk()->assertJsonPath('data.total', 25)->assertJsonCount(20, 'data.items')->json('data');
        $queries = DB::getQueryLog();
        $this->assertCount(0, array_filter($queries, fn ($q) => str_contains($q['query'], 'audit_events') || str_contains($q['query'], 'todo_notifications')));
        foreach ($page['items'] as $todo) {
            foreach (['content', 'note', 'history', 'legacy_attributes'] as $field) {
                $this->assertArrayNotHasKey($field, $todo);
            }
            $this->assertSame($this->alice['id'], $todo['assigneeId']);
            $this->assertTrue($todo['capabilities']['edit']);
        }
        $next = $this->getJson('/api/v1/todos?page=2')->assertOk()->assertJsonCount(5, 'data.items')->json('data.items');
        $this->assertSame([], array_intersect(array_column($page['items'], 'id'), array_column($next, 'id')));
        $this->getJson('/api/v1/todos/counts')->assertOk()->assertJsonPath('data.mineOpen', 25)->assertJsonPath('data.sentOpen', 0);
        $version = $this->getJson('/api/v1/personal-version')->assertOk()->json('data');
        $this->assertSame($version, $this->getJson('/api/v1/personal-version')->assertOk()->json('data'));
        $this->rpc('updateTodo', ['id' => $ids[0], 'expectedRevision' => 1, 'content' => 'Edited content'])->assertOk();
        $this->assertNotSame($version['todos'], $this->getJson('/api/v1/personal-version')->assertOk()->json('data.todos'));
        $detail = $this->getJson('/api/v1/todos/'.$ids[0])->assertOk()->json('data');
        $this->assertSame('Edited content', $detail['content']);
        $this->assertArrayNotHasKey('history', $detail);
        $this->getJson('/api/v1/todos/'.$ids[0].'/history')->assertOk()->assertJsonCount(2, 'data.items');
        $this->getJson('/api/v1/todos?perPage=101')->assertStatus(400);
        $this->getJson('/api/v1/todos?scope=other')->assertStatus(400);
        $this->getJson('/api/v1/todos', ['X-Workspace-ID' => 'other'])->assertStatus(409);
        $this->getJson('/api/v1/todo-assignees?search=Bob')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $this->bob['id']);
        $this->login($this->bob);
        $this->getJson('/api/v1/todos')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/todos/'.$ids[0])->assertStatus(404);
        $this->getJson('/api/v1/todos/'.$ids[0].'/history')->assertStatus(404);
        $this->login($this->owner);
        $assigned = $this->todo('Assigned', $this->alice['id']);
        $this->login($this->alice);
        $this->getJson('/api/v1/todo-notifications/unread')->assertOk()->assertJsonPath('data.unread', 1);
        $this->getJson('/api/v1/todo-notifications')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.todoId', $assigned['id']);
        $this->rpc('returnTodo', ['id' => $assigned['id'], 'expectedRevision' => 1])->assertOk();
        $this->getJson('/api/v1/todo-notifications/unread')->assertOk()->assertJsonPath('data.unread', 0);
        $this->getJson('/api/v1/todo-notifications')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/todos/'.$assigned['id'])->assertStatus(404);
        $this->login($this->owner);
        $this->getJson('/api/v1/todos?scope=sent')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.returnedAt', $this->tenant->require('todos', $assigned['id'])['returnedAt']);
    }

    /**
     * 轉卡連結批次重驗Project資格，移出Project後不讀或暴露卡片識別。
     *
     * @return void
     */
    public function test_converted_links_are_scoped_and_reauthorized_in_batch(): void
    {
        $this->login($this->owner);
        $project = $this->rpc('createProject', ['name' => 'Converted', 'code' => 'CONVERT'])->assertOk()->json('data');
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $this->alice['id'], 'roleId' => 'developer', 'expectedRevision' => 0])->assertOk();
        $this->login($this->alice);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/todos?state=closed')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'from "issues"')));
        $this->login($this->owner);
        $todo = $this->todo('Convert small task', $this->alice['id']);
        $this->login($this->alice);
        $this->rpc('convertTodo', ['id' => $todo['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id'], 'title' => 'Converted card', 'type' => 'task', 'state' => 'open']])->assertOk();
        $this->getJson('/api/v1/todos?state=closed')->assertOk()->assertJsonPath('data.items.0.convertedIssue.projectId', $project['id']);
        $source = Todo::where('public_id', $todo['id'])->firstOrFail();
        for ($index = 0; $index < 19; $index++) {
            $copy = $source->replicate();
            $copy->public_id = (string) Str::uuid();
            $copy->save();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/todos?state=closed')->assertOk()->assertJsonPath('data.total', 20)->assertJsonCount(20, 'data.items');
        $this->assertCount(1, array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'from "issues"')));
        for ($index = 20; $index < 200; $index++) {
            $copy = $source->replicate();
            $copy->public_id = (string) Str::uuid();
            $copy->save();
        }
        DB::flushQueryLog();
        $this->getJson('/api/v1/todos?state=closed&perPage=100')->assertOk()->assertJsonPath('data.total', 200)->assertJsonCount(100, 'data.items');
        $this->assertCount(1, array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'from "issues"')));
        $version = $this->getJson('/api/v1/personal-version')->assertOk()->json('data.todos');
        $this->login($this->owner);
        $this->rpc('removeProjectMember', ['projectId' => $project['id'], 'userId' => $this->alice['id'], 'expectedRevision' => 1])->assertOk();
        $this->login($this->alice);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertNotSame($version, $this->getJson('/api/v1/personal-version')->assertOk()->json('data.todos'));
        DB::flushQueryLog();
        $this->getJson('/api/v1/todos?state=closed')->assertOk()->assertJsonPath('data.items.0.convertedIssue', null);
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'from "issues"')));
        $this->getJson('/api/v1/todos/'.$todo['id'])->assertOk()->assertJsonPath('data.convertedIssue', null);
    }
}
