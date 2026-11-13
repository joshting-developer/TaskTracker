<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** TT-315：公開API「我的通知」只回本人的任務通知、遞增續讀不重複不漏、撤專案權限後讀不到；SQLite與獨立MySQL共用。 */
trait PublicNotificationCases
{
    /**
     * 建立一般成員, 回傳登入用資料。
     *
     * @param  string  $workspaceId  工作空間ID
     * @param  string  $name  顯示名稱
     * @return array{id: string, email: string}
     */
    private function noticeMember(string $workspaceId, string $name): array
    {
        $user = User::create(['id' => (string) Str::uuid(), 'name' => $name, 'email' => strtolower($name).'-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        (new RecordStore)->forWorkspace($workspaceId)->put('members', $user->id, ['id' => $user->id, 'name' => $name, 'email' => $user->email, 'role' => 'user']);

        return ['id' => $user->id, 'email' => $user->email];
    }

    /**
     * 換人登入。
     *
     * @param  string  $email  Email
     * @return void
     */
    private function noticeLogin(string $email): void
    {
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'test-local-password'])->assertOk();
    }

    /**
     * Session RPC並補requestId。
     *
     * @param  string  $op  RPC操作
     * @param  array<string,mixed>  $input  輸入
     * @return array<string,mixed> 回應data
     */
    private function noticeRpc(string $op, array $input): array
    {
        return $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input])->assertOk()->json('data');
    }

    /**
     * Agent被指派、卡被留言、狀態改成已關閉, 用read Token讀到三則；讀不到別人的；limit續讀不重複不漏；撤專案權限後讀不到。
     *
     * @return void
     */
    public function test_public_notifications_mine_cursor_and_revocation(): void
    {
        $ownerEmail = 'notice-'.Str::uuid().'@example.test';
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => $ownerEmail, 'password' => 'test-local-password', 'workspaceName' => 'Notice'])->assertOk()->json('data');
        $workspace = $owner['workspace']['id'];
        $agent = $this->noticeMember($workspace, 'Agent');
        $mei = $this->noticeMember($workspace, 'Mei');
        $project = $this->noticeRpc('createProject', ['name' => 'Notice', 'code' => 'NTC']);
        $acl = $this->noticeRpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $agent['id'], 'roleId' => 'developer', 'expectedRevision' => 0]);
        $acl = $this->noticeRpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $mei['id'], 'roleId' => 'developer', 'expectedRevision' => $acl['revision']]);

        $this->noticeLogin($agent['email']);
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'agent-read', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $as = ['Authorization' => 'Bearer '.$token];
        $this->assertSame(['items' => [], 'nextAfter' => null, 'hasMore' => false], $this->getJson('/api/public/v1/notifications', $as)->assertOk()->json('data'), '還沒有通知');

        $this->noticeLogin($ownerEmail);
        $issue = $this->noticeRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Agent card', 'assignee' => $agent['id']]);
        $commented = $this->noticeRpc('addComment', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'content' => '請看一下']);
        $this->noticeRpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => $commented['revision'], 'state' => 'closed']);
        $this->noticeRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Mei card', 'assignee' => $mei['id']]);

        $all = $this->getJson('/api/public/v1/notifications', $as)->assertOk()->json('data');
        $this->assertSame(['assigned', 'comment', 'status_important'], array_column($all['items'], 'kind'), '依產生順序, 只有寄給Agent的');
        $this->assertSame(['id', 'kind', 'subject', 'projectId', 'issueId', 'identifier', 'createdAt'], array_keys($all['items'][0]), '摘要不帶正文和Email');
        $this->assertSame([$issue['id']], array_values(array_unique(array_column($all['items'], 'issueId'))));
        $this->assertSame($project['id'], $all['items'][0]['projectId']);
        $this->assertFalse($all['hasMore']);

        // limit=1一筆一筆續讀, 接起來跟一次讀完一樣；讀到底後位置不變。
        $seen = [];
        $after = null;
        do {
            $page = $this->getJson('/api/public/v1/notifications?limit=1'.($after === null ? '' : '&after='.$after), $as)->assertOk()->json('data');
            array_push($seen, ...array_column($page['items'], 'id'));
            $after = $page['nextAfter'];
        } while ($page['hasMore']);
        $this->assertSame(array_column($all['items'], 'id'), $seen);
        $this->assertSame(['items' => [], 'nextAfter' => $after, 'hasMore' => false], $this->getJson('/api/public/v1/notifications?after='.$after, $as)->assertOk()->json('data'));

        // 新通知從上次位置接著讀到。
        $this->noticeRpc('addComment', ['id' => $issue['id'], 'expectedRevision' => $commented['revision'] + 1, 'content' => '再補一句']);
        $next = $this->getJson('/api/public/v1/notifications?after='.$after, $as)->assertOk()->json('data');
        $this->assertSame(['comment'], array_column($next['items'], 'kind'));

        foreach (['limit=0', 'limit=101', 'limit=x', 'after=0', 'after=-1', 'after=x'] as $query) {
            $this->getJson('/api/public/v1/notifications?'.$query, $as)->assertStatus(400);
        }
        $this->getJson('/api/public/v1/notifications')->assertStatus(401);

        // 拿掉Agent的專案資格：這個專案的通知都讀不到, 位置照樣往前。
        $this->noticeRpc('removeProjectMember', ['projectId' => $project['id'], 'userId' => $agent['id'], 'expectedRevision' => $acl['revision']]);
        $revoked = $this->getJson('/api/public/v1/notifications', $as)->assertOk()->json('data');
        $this->assertSame([], $revoked['items']);
        $this->assertSame($next['nextAfter'], $revoked['nextAfter']);
    }
}
