<?php

namespace Tests\Concerns;

use App\Data\PageQueryData;
use App\Data\PersonalReadContext;
use App\Models\Todo;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Repositories\RecordStore;
use App\Repositories\TodoCollaborationRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** 待辦協作儲存只用隔離人造帳號, 驗證查詢範圍及外鍵 */
trait TodoCollaborationStorageCases
{
    /** 建立已驗證帳號、同空間成員及一筆待辦
     * @return array{TodoCollaborationRepository,PersonalReadContext,Todo,WorkspaceMember} 資料入口及合成角色
     */
    private function collaborationStorageFixture(): array
    {
        Queue::fake();
        $identity = $this->postJson('/api/auth/register', ['name' => 'Sender', 'email' => Str::uuid().'@example.test', 'password' => 'collaboration-test-password', 'workspaceName' => 'Collaboration'])->assertOk()->json('data');
        $workspace = Workspace::where('public_id', $identity['workspace']['id'])->firstOrFail();
        $sender = WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $identity['user']['id'])->firstOrFail();
        $receiver = User::factory()->create(['id' => (string) Str::uuid()]);
        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        $store->put('members', $receiver->id, ['id' => $receiver->id, 'name' => $receiver->name, 'email' => $receiver->email, 'role' => 'user', 'joinedAt' => now()->toISOString()]);
        $member = WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $receiver->id)->firstOrFail();
        $result = $this->postJson('/api/rpc/createTodo', ['title' => 'Storage fixture', 'assigneeId' => $receiver->id, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $repo = app(TodoCollaborationRepository::class);
        $context = new PersonalReadContext($workspace->id, $sender->id, $sender->user_id);
        $todo = $repo->lock($context, $result['id']);

        return [$repo, $context, $todo, $member];
    }

    /** 初始化去重, 退回路徑不移除曾經手資格
     * @return void
     */
    public function test_collaboration_storage_path_and_participants_are_idempotent(): void
    {
        [$repo,$context,$todo,$receiver] = $this->collaborationStorageFixture();
        $repo->initialize($todo);
        $repo->initialize($todo);
        $this->assertSame(2, DB::table('todo_participants')->where('todo_id', $todo->id)->count());
        $this->assertSame(2, count($repo->tail($todo)));
        $repo->pop($todo, 1);
        $this->assertTrue($repo->participates(new PersonalReadContext($context->workspaceId, $receiver->id, $receiver->user_id), $todo));
        $this->assertSame(1, count($repo->tail($todo)));
        $this->assertFalse($repo->participates(new PersonalReadContext($context->workspaceId, 999999, (string) Str::uuid()), $todo));
        $this->assertNull($repo->lock(new PersonalReadContext(999999, $context->memberId, $context->userId), $todo->public_id));
    }

    /** 留言只按單一待辦穩定分頁, 不回工作區或原稿快照
     * @return void
     */
    public function test_collaboration_storage_pages_are_bounded_and_scoped(): void
    {
        [$repo,$context,$todo] = $this->collaborationStorageFixture();
        for ($i = 0; $i < 25; $i++) {
            $repo->append('todo_comments', ['public_id' => (string) Str::uuid(), 'workspace_id' => $context->workspaceId, 'todo_id' => $todo->id, 'author_id' => $context->memberId, 'author_name' => 'Sender', 'content' => 'Note '.$i, 'created_at' => now()]);
        }
        $page = $repo->page($todo, 'todo_comments', new PageQueryData(1, 20, ''));
        $this->assertSame(25, $page->total());
        $this->assertCount(20, $page->items());
        $this->assertSame('Note 0', $page->items()[0]->content);
        $this->assertSame('Note 19', $page->items()[19]->content);
        $this->assertFalse(property_exists($page->items()[0], 'workspace_id'));
        $next = $repo->page($todo, 'todo_comments', new PageQueryData(2, 20, ''));
        $this->assertCount(5, $next->items());
        $this->assertSame('Note 20', $next->items()[0]->content);
    }

    /** 資料庫拒絕跨工作區留言, 不只靠前端或PHP判斷
     * @return void
     */
    public function test_collaboration_storage_rejects_cross_workspace_reference(): void
    {
        [$repo,$context,$todo] = $this->collaborationStorageFixture();
        try {
            DB::transaction(fn () => $repo->append('todo_comments', ['public_id' => (string) Str::uuid(), 'workspace_id' => $context->workspaceId + 1000000, 'todo_id' => $todo->id, 'author_id' => $context->memberId, 'author_name' => 'Other', 'content' => 'Forbidden', 'created_at' => now()]));
            $this->fail('Cross-workspace reference accepted');
        } catch (QueryException) {
            $this->assertSame(0,DB::table('todo_comments')->where('todo_id',$todo->id)->count());
        }
    }
}
