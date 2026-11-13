<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** TT-311：公開API小待辦的Token範圍、交辦、完成／退回、重送與有界列表；SQLite與獨立MySQL共用。 */
trait PublicTodoApiCases
{
    /**
     * 在目前工作空間建立第二個一般成員並登入, 建立他的Token後換回原本的人。
     *
     * @param  array<string,mixed>  $owner  註冊回應
     * @return array{0: array<string,string>, 1: string} 第二個成員與他的write Token
     */
    private function secondMemberWithToken(array $owner): array
    {
        $user = User::create(['id' => (string) Str::uuid(), 'name' => 'Agent', 'email' => 'agent-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        $member = ['id' => $user->id, 'name' => 'Agent', 'email' => $user->email, 'role' => 'user'];
        (new RecordStore)->forWorkspace($owner['workspace']['id'])->put('members', $user->id, $member);
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $member['email'], 'password' => 'test-local-password'])->assertOk();
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'agent', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        Auth::logout();

        return [$member, $token];
    }

    /**
     * 擁有者用Token交辦給Agent；Agent用自己的Token看到交辦給他的待辦並完成；read Token不能寫；重送回原結果。
     *
     * @return void
     */
    public function test_public_todo_assign_read_complete_and_scopes(): void
    {
        $email = 'todo-owner-'.Str::uuid().'@example.test';
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => 'Todo'])->assertOk()->json('data');
        $ownerWrite = $this->postJson('/api/v1/personal-tokens', ['name' => 'owner-write', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $ownerRead = $this->postJson('/api/v1/personal-tokens', ['name' => 'owner-read', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        [$agent, $agentToken] = $this->secondMemberWithToken($owner);
        $as = fn (string $token) => ['Authorization' => 'Bearer '.$token];

        $assignees = $this->getJson('/api/public/v1/todo-assignees', $as($ownerWrite))->assertOk()->json('data.items');
        $this->assertContains($agent['id'], array_column($assignees, 'id'));
        $this->assertSame(['id', 'name'], array_keys($assignees[0]), '交辦對象不回Email');

        $this->json('POST', '/api/public/v1/todos', ['title' => '不能寫', 'requestId' => (string) Str::uuid()], $as($ownerRead))->assertStatus(403);
        $this->json('POST', '/api/public/v1/todos', ['title' => '缺requestId'], $as($ownerWrite))->assertStatus(400);
        $input = ['content' => "請複查TT-311\n\n完成條件：看過OpenAPI", 'assigneeId' => $agent['id'], 'requestId' => (string) Str::uuid()];
        $created = $this->json('POST', '/api/public/v1/todos', $input, $as($ownerWrite))->assertOk()->json('data');
        $this->assertSame(['id', 'revision'], array_keys($created));
        $this->assertEquals($created, $this->json('POST', '/api/public/v1/todos', $input, $as($ownerWrite))->assertOk()->json('data'), '同requestId重送回原結果');

        $mine = $this->getJson('/api/public/v1/todos?scope=mine&state=open', $as($agentToken))->assertOk()->json('data');
        $this->assertSame([$created['id']], array_column($mine['items'], 'id'), '重送沒有建立第二筆');
        $this->assertSame('請複查TT-311', $mine['items'][0]['title']);
        $this->getJson('/api/public/v1/todos/'.$created['id'], $as($agentToken))->assertOk()->assertJsonPath('data.content', $input['content']);
        $this->assertSame([$created['id']], array_column($this->getJson('/api/public/v1/todos?scope=sent', $as($ownerWrite))->assertOk()->json('data.items'), 'id'));
        $this->getJson('/api/public/v1/todos?perPage=101', $as($agentToken))->assertStatus(400);
        $this->getJson('/api/public/v1/todos?scope=everyone', $as($agentToken))->assertStatus(400);

        // 交辦人不能代替承辦人完成；承辦人版本錯誤409；正確版本完成。
        $this->json('POST', '/api/public/v1/todos/'.$created['id'].'/complete', ['expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $as($ownerWrite))->assertStatus(403);
        $this->json('POST', '/api/public/v1/todos/'.$created['id'].'/complete', ['expectedRevision' => 9, 'requestId' => (string) Str::uuid()], $as($agentToken))->assertStatus(409);
        $done = $this->json('POST', '/api/public/v1/todos/'.$created['id'].'/complete', ['expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $as($agentToken))->assertOk()->json('data');
        $this->assertSame(2, $done['revision']);
        $this->assertSame('done', $this->getJson('/api/public/v1/todos/'.$created['id'], $as($agentToken))->json('data.state'));
        $this->json('POST', '/api/public/v1/todos/'.$created['id'].'/reopen', ['expectedRevision' => 2, 'requestId' => (string) Str::uuid()], $as($agentToken))->assertOk()->assertJsonPath('data.revision', 3);
    }

    /**
     * 退回給交辦人、交辦人修改內容；看不到別人的私人待辦。
     *
     * @return void
     */
    public function test_public_todo_return_update_and_privacy(): void
    {
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'todo-priv-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Private'])->assertOk()->json('data');
        $ownerToken = $this->postJson('/api/v1/personal-tokens', ['name' => 'owner', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        [$agent, $agentToken] = $this->secondMemberWithToken($owner);
        $as = fn (string $token) => ['Authorization' => 'Bearer '.$token];

        $private = $this->json('POST', '/api/public/v1/todos', ['title' => '我自己的待辦', 'requestId' => (string) Str::uuid()], $as($ownerToken))->assertOk()->json('data');
        $this->getJson('/api/public/v1/todos/'.$private['id'], $as($agentToken))->assertStatus(404);
        $this->assertSame([], $this->getJson('/api/public/v1/todos?scope=mine', $as($agentToken))->json('data.items'));

        $task = $this->json('POST', '/api/public/v1/todos', ['title' => '幫忙跑測試', 'assigneeId' => $agent['id'], 'requestId' => (string) Str::uuid()], $as($ownerToken))->assertOk()->json('data');
        $returned = $this->json('POST', '/api/public/v1/todos/'.$task['id'].'/return', ['expectedRevision' => 1, 'note' => '缺環境資訊', 'requestId' => (string) Str::uuid()], $as($agentToken))->assertOk()->json('data');
        $this->assertSame(2, $returned['revision']);
        $back = $this->getJson('/api/public/v1/todos/'.$task['id'], $as($ownerToken))->assertOk()->json('data');
        $this->assertSame($owner['user']['id'], $back['assigneeId'], '退回後回到交辦人');
        $this->json('PATCH', '/api/public/v1/todos/'.$task['id'], ['expectedRevision' => 2, 'title' => '幫忙跑測試（附環境）', 'requestId' => (string) Str::uuid()], $as($ownerToken))->assertOk()->assertJsonPath('data.revision', 3);
        $this->json('PATCH', '/api/public/v1/todos/'.$task['id'], ['expectedRevision' => 3, 'title' => '別人改', 'requestId' => (string) Str::uuid()], $as($agentToken))->assertStatus(404);
    }
}
