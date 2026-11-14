<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** 本人通知批次已讀範圍與重播測試 */
trait TodoNotificationReadCases
{
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

    /** 一次更新跨頁通知, 不改他人／不可見／其他空間, 重試不誤讀新通知
     * @return void
     */
    public function test_read_all_notifications_is_scoped_bulk_and_replay_safe(): void
    {
        for ($i = 0; $i < 23; $i++) {
            $this->todo('Assigned '.$i, $this->alice['id']);
        }
        $bobTodo = $this->todo('Bob private', $this->bob['id']);
        $hidden = (string) Str::uuid();
        $this->tenant->put('todoNotifications', $hidden, ['id' => $hidden, 'todoId' => $bobTodo['id'], 'recipientId' => $this->alice['id'], 'actor' => 'Owner', 'event' => 'assigned', 'createdAt' => now()->toISOString(), 'readAt' => null]);
        $firstWorkspace = session('workspace_id');
        Auth::logout();
        $this->postJson('/api/auth/register', ['name' => 'Other', 'email' => 'notification-other@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Other'])->assertOk();
        $other = (new RecordStore)->forWorkspace(session('workspace_id'));
        $other->put('members', $this->alice['id'], $this->alice);
        $otherTodo = $this->todo('Other tenant', $this->alice['id']);
        $this->login($this->alice);
        $this->withSession(['workspace_id' => $firstWorkspace]);
        $this->getJson('/api/v1/todo-notifications/unread')->assertJsonPath('data.unread', 23);
        $key = (string) Str::uuid();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->postJson('/api/v1/todo-notifications/read-all', ['requestId' => $key])->assertOk()->assertJsonPath('data.updated', 23);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $writes = array_filter($queries, fn ($q) => str_starts_with(strtolower($q['query']), 'update') && str_contains($q['query'], 'todo_notifications'));
        $this->assertCount(1, $writes);
        $this->assertCount(0, array_filter($queries, fn ($q) => str_starts_with(strtolower($q['query']), 'select') && str_contains($q['query'], 'todo_notifications')));
        $this->assertNull($this->tenant->require('todoNotifications', $hidden)['readAt']);
        $bobNotification = array_values(array_filter($this->tenant->all('todoNotifications'), fn ($n) => $n['recipientId'] === $this->bob['id']))[0];
        $this->assertNull($bobNotification['readAt']);
        $this->assertNull($other->all('todoNotifications')[0]['readAt']);
        $this->getJson('/api/v1/todo-notifications/unread')->assertJsonPath('data.unread', 0);
        $oldTimes = array_column($this->tenant->all('todoNotifications'), 'readAt', 'id');
        $this->login($this->owner);
        $this->withSession(['workspace_id' => $firstWorkspace]);
        $this->todo('Later arrival', $this->alice['id']);
        $this->login($this->alice);
        $this->withSession(['workspace_id' => $firstWorkspace]);
        $this->postJson('/api/v1/todo-notifications/read-all', ['requestId' => $key])->assertOk()->assertJsonPath('data.updated', 23);
        $this->getJson('/api/v1/todo-notifications/unread')->assertJsonPath('data.unread', 1);
        $this->postJson('/api/v1/todo-notifications/read-all', ['requestId' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.updated', 1);
        foreach ($this->tenant->all('todoNotifications') as $n) {
            if (! empty($oldTimes[$n['id']])) {
                $this->assertSame($oldTimes[$n['id']], $n['readAt']);
            }
        }
        $this->tenant->put('members', $this->alice['id'], [...$this->alice, 'disabledAt' => now()->toISOString()]);
        $this->postJson('/api/v1/todo-notifications/read-all', ['requestId' => $key])->assertStatus(401);
    }

    /** 拒絕外部scope與無效操作碼, 跨分頁租戶不能沿用
     * @return void
     */
    public function test_read_all_notifications_rejects_untrusted_scope_and_tenant_header(): void
    {
        $this->todo('For Alice', $this->alice['id']);
        $this->login($this->alice);
        $this->postJson('/api/v1/todo-notifications/read-all', ['requestId' => 'invalid'])->assertStatus(400);
        $this->postJson('/api/v1/todo-notifications/read-all', ['requestId' => (string) Str::uuid(), 'recipientId' => $this->bob['id']])->assertStatus(400);
        $this->withHeader('X-Workspace-ID', (string) Str::uuid())->postJson('/api/v1/todo-notifications/read-all', ['requestId' => (string) Str::uuid()])->assertStatus(409);
        $this->assertNull($this->tenant->all('todoNotifications')[0]['readAt']);
    }
}
