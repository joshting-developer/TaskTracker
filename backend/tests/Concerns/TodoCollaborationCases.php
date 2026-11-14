<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\WorkspaceMember;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** Session與PAT共用合成三人交接鏈, 驗證經手隱私與原子回覆 */
trait TodoCollaborationCases
{
    /** 建立四位同空間成員, 第四位為未經手的Owner
     * @return array{array,RecordStore,array} 建立人、範圍Store及角色
     */
    private function collaborationFixture(): array
    {
        Queue::fake();
        $owner = $this->postJson('/api/auth/register', ['name' => 'Claude', 'email' => Str::uuid().'@example.test', 'password' => 'collaboration-test-password', 'workspaceName' => 'Collaboration'])->assertOk()->json('data');
        $store = (new RecordStore)->forWorkspace($owner['workspace']['id']);
        $people = ['Claude' => $owner['user']];
        foreach (['Josh' => 'user', 'Codex' => 'user', 'Stranger' => 'owner'] as $name => $role) {
            $user = User::create(['id' => (string) Str::uuid(), 'name' => $name, 'email' => Str::uuid().'@example.test', 'password' => Hash::make('collaboration-test-password')]);
            $people[$name] = ['id' => $user->id, 'name' => $name, 'email' => $user->email];
            $store->put('members', $user->id, [...$people[$name], 'role' => $role, 'joinedAt' => now()->toISOString()]);
        }

        return [$owner, $store, $people];
    }

    /** 以正式登入切換合成人員
     * @param  array  $person  合成帳號
     * @return void
     */
    private function collaborationLogin(array $person): void
    {
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $person['email'], 'password' => 'collaboration-test-password'])->assertOk();
    }

    /** 走一次Claude→Josh評語轉Codex→回覆完成, 經手者可讀且重送不重複
     * @return void
     */
    public function test_todo_forward_comment_complete_and_participant_visibility(): void
    {
        [$owner,$store,$people] = $this->collaborationFixture();
        $todo = $this->postJson('/api/rpc/createTodo', ['title' => 'Review then implement', 'assigneeId' => $people['Josh']['id'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->collaborationLogin($people['Josh']);
        $forward = ['id' => $todo['id'], 'expectedRevision' => 1, 'assigneeId' => $people['Codex']['id'], 'reply' => '決定採A方案', 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/forwardTodo', $forward)->assertOk()->assertJsonPath('data.revision', 2);
        $this->postJson('/api/rpc/forwardTodo', $forward)->assertOk()->assertJsonPath('data.revision', 2);
        $this->getJson('/api/v1/todos/'.$todo['id'].'/comments')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.content', '決定採A方案');
        $this->collaborationLogin($people['Codex']);
        $this->getJson('/api/v1/todos/'.$todo['id'].'/handoffs')->assertOk()->assertJsonPath('data.items.0.from', 'Josh')->assertJsonPath('data.items.0.to', 'Codex');
        $complete = ['id' => $todo['id'], 'expectedRevision' => 2, 'reply' => '已完成並測試', 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/completeTodo', $complete)->assertOk()->assertJsonPath('data.revision', 3);
        $this->postJson('/api/rpc/completeTodo', $complete)->assertOk();
        $this->collaborationLogin($people['Josh']);
        $this->getJson('/api/v1/todos?scope=involved&state=closed')->assertOk()->assertJsonPath('data.items.0.id', $todo['id']);
        $this->getJson('/api/v1/todos/'.$todo['id'].'/comments')->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.items.1.author', 'Codex');
        $this->postJson('/api/rpc/commentTodo', ['id' => $todo['id'], 'content' => '驗收通過', 'expectedRevision' => 3, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->collaborationLogin($people['Claude']);
        $this->getJson('/api/v1/todos/'.$todo['id'])->assertOk()->assertJsonPath('data.state', 'done');
        $this->collaborationLogin($people['Stranger']);
        $this->getJson('/api/v1/todos/'.$todo['id'])->assertStatus(404);
        $this->getJson('/api/v1/todos/'.$todo['id'].'/comments')->assertStatus(404);
        $this->postJson('/api/rpc/commentTodo', ['id' => $todo['id'], 'content' => 'Unauthorized', 'expectedRevision' => 4, 'requestId' => (string) Str::uuid()])->assertStatus(404);
    }

    /** 紙條四分頁使用同一SQL分頁, sent包含自己建立的紙條而all仍限定參與範圍
     * @return void
     */
    public function test_note_list_all_states_is_bounded_and_sent_includes_self(): void
    {
        [$owner, $store, $people] = $this->collaborationFixture();
        $mine = $this->postJson('/api/rpc/createTodo', ['content' => "自己的紙條\n\n不應出現在列表的原稿", 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/rpc/completeTodo', ['id' => $mine['id'], 'expectedRevision' => $mine['revision'], 'requestId' => (string) Str::uuid()])->assertOk();
        $other = $this->postJson('/api/rpc/createTodo', ['content' => '交辦紙條', 'assigneeId' => $people['Josh']['id'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $first = $this->getJson('/api/v1/todos?scope=sent&state=all&perPage=1')->assertOk()->assertJsonPath('data.total', 2)->assertJsonCount(1, 'data.items')->json('data.items.0');
        $second = $this->getJson('/api/v1/todos?scope=sent&state=all&perPage=1&page=2')->assertOk()->assertJsonCount(1, 'data.items')->json('data.items.0');
        $this->assertEqualsCanonicalizing([$mine['id'], $other['id']], [$first['id'], $second['id']]);
        foreach ([$first, $second] as $row) {
            foreach (['content', 'note', 'history', 'comments', 'handoffs'] as $field) {
                $this->assertArrayNotHasKey($field, $row);
            }
        }
        $this->getJson('/api/v1/todos?scope=mine&state=open')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/todos/counts')->assertOk()->assertJsonPath('data.sentOpen', 1)->assertJsonPath('data.sentClosed', 1);
        $this->collaborationLogin($people['Stranger']);
        $this->getJson('/api/v1/todos?scope=involved&state=all')->assertOk()->assertJsonPath('data.total', 0);
    }

    /** 退回沿目前路徑逐層返回, 不回到已停用的上一手
     * @return void
     */
    public function test_todo_return_uses_previous_handler_and_preserves_history(): void
    {
        [$owner,$store,$people] = $this->collaborationFixture();
        $todo = $this->postJson('/api/rpc/createTodo', ['title' => 'Return path', 'assigneeId' => $people['Josh']['id'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->collaborationLogin($people['Josh']);
        $this->postJson('/api/rpc/forwardTodo', ['id' => $todo['id'], 'assigneeId' => $people['Codex']['id'], 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->collaborationLogin($people['Codex']);
        $this->postJson('/api/rpc/returnTodo', ['id' => $todo['id'], 'expectedRevision' => 2, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson('/api/v1/todos/'.$todo['id'])->assertJsonPath('data.assigneeId', $people['Josh']['id']);
        $this->collaborationLogin($people['Josh']);
        $this->postJson('/api/rpc/returnTodo', ['id' => $todo['id'], 'expectedRevision' => 3, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson('/api/v1/todos/'.$todo['id'])->assertJsonPath('data.assigneeId', $people['Claude']['id']);
        $this->getJson('/api/v1/todos/'.$todo['id'].'/handoffs')->assertJsonPath('data.total', 3);
    }

    /** 轉發或版本驗證失敗時不得留下半則留言, 經手者失去工作區資格後不能重播
     * @return void
     */
    public function test_todo_failed_forward_is_atomic_and_disabled_handler_cannot_replay(): void
    {
        [$owner,$store,$people] = $this->collaborationFixture();
        $todo = $this->postJson('/api/rpc/createTodo', ['title' => 'Atomic', 'assigneeId' => $people['Josh']['id'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->collaborationLogin($people['Josh']);
        $bad = ['id' => $todo['id'], 'assigneeId' => (string) Str::uuid(), 'reply' => '不可殘留', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/forwardTodo', $bad)->assertStatus(400);
        $this->getJson('/api/v1/todos/'.$todo['id'].'/comments')->assertJsonPath('data.total', 0);
        $good = [...$bad, 'assigneeId' => $people['Codex']['id'], 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/forwardTodo', $good)->assertOk();
        WorkspaceMember::where('user_id', $people['Josh']['id'])->update(['disabled_at' => now()]);
        $this->postJson('/api/rpc/forwardTodo', $good)->assertStatus(401);
        $this->getJson('/api/v1/todos/'.$todo['id'].'/comments')->assertStatus(401);
    }

    /** 公開PAT也走同一條三人鏈, read範圍不能轉發且重播後不重複事件
     * @return void
     */
    public function test_public_todo_collaboration_scopes_replay_and_revocation(): void
    {
        [$owner,$store,$people] = $this->collaborationFixture();
        $tokens = [];
        foreach ($people as $name => $person) {
            $this->collaborationLogin($person);
            $tokens[$name] = $this->postJson('/api/v1/personal-tokens', ['name' => 'Collaboration '.$name, 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        }
        $this->collaborationLogin($people['Josh']);
        $read = $this->postJson('/api/v1/personal-tokens', ['name' => 'Read', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $headers = fn ($name) => ['Authorization' => 'Bearer '.$tokens[$name]];
        $todo = $this->json('POST', '/api/public/v1/todos', ['title' => 'Public chain', 'assigneeId' => $people['Josh']['id'], 'requestId' => (string) Str::uuid()], $headers('Claude'))->assertOk()->json('data');
        $base = '/api/public/v1/todos/'.$todo['id'];
        $forward = ['assigneeId' => $people['Codex']['id'], 'reply' => '照這個方向做', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()];
        $this->getJson($base, ['Authorization' => 'Bearer '.$read])->assertOk()->assertJsonPath('data.capabilities.forward', false)->assertJsonPath('data.capabilities.comment', false);
        $this->json('POST', $base.'/forward', $forward, ['Authorization' => 'Bearer '.$read])->assertStatus(403);
        $this->json('POST', $base.'/forward', $forward, $headers('Josh'))->assertOk()->assertJsonPath('data.revision', 2);
        $this->json('POST', $base.'/forward', $forward, $headers('Josh'))->assertOk();
        $this->getJson($base.'/comments', $headers('Codex'))->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.author', 'Josh');
        $this->json('POST', $base.'/complete', ['reply' => 'CLI已處理', 'expectedRevision' => 2, 'requestId' => (string) Str::uuid()], $headers('Codex'))->assertOk();
        $this->getJson($base.'/comments', $headers('Claude'))->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson($base.'/handoffs', $headers('Josh'))->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson($base.'/comments', $headers('Stranger'))->assertStatus(404);
        $changed = [...$forward, 'reply' => '改寫重播'];
        $this->json('POST', $base.'/forward', $changed, $headers('Josh'))->assertStatus(409);
        WorkspaceMember::where('user_id', $people['Josh']['id'])->update(['disabled_at' => now()]);
        $this->json('POST', $base.'/forward', $forward, $headers('Josh'))->assertStatus(401);
    }
}
