<?php

namespace Tests\Feature;

use App\Domain\DomainError;
use App\Repositories\RecordStore;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectBoardsTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'boards@example.test'): array
    {
        return $this->postJson('/api/auth/register', ['name' => 'Board user', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => '團隊'])->assertOk()->json('data');
    }
    private function rpc(string $op, array $input = []) { return $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input]); }
    private function project(string $code = 'BOARD'): array { return $this->rpc('createProject', ['name' => '專案', 'code' => $code])->assertOk()->json('data'); }
    private function issue(array $project, array $fields = []): array { return $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '卡片', ...$fields])->assertOk()->json('data'); }

    public function test_default_boards_and_custom_board_validation_revisions_and_retries(): void
    {
        $this->register();
        $project = $this->project(); $other = $this->project('OTHER');
        $task = $this->issue($project); $external = $this->issue($other);
        $data = $this->rpc('getWorkspace')->assertOk()->json('data.boards');
        $this->assertCount(2, $data);
        $this->assertSame(['default'], array_values(array_unique(array_column($data, 'id'))));
        $this->rpc('updateBoard', ['id' => 'default', 'expectedRevision' => 0, 'name' => '改'])->assertStatus(400);
        $this->rpc('archiveBoard', ['id' => 'default', 'expectedRevision' => 0])->assertStatus(400);
        $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => '錯誤', 'selectedIssueIds' => [$external['id']]])->assertStatus(400);
        $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => '重複', 'selectedIssueIds' => [$task['id'], $task['id']]])->assertStatus(400);
        $input = ['projectId' => $project['id'], 'name' => '自訂', 'selectedIssueIds' => [$task['id']], 'requestId' => 'create-board-once'];
        $board = $this->rpc('createBoard', $input)->assertOk()->json('data');
        $this->rpc('createBoard', $input)->assertJsonPath('data.id', $board['id']);
        $this->rpc('createBoard', [...$input, 'name' => '不同'])->assertStatus(409);
        $this->rpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => 1, 'projectId' => $other['id'], 'name' => '換專案'])->assertStatus(400);
        $this->rpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => 1, 'name' => '空看板', 'selectedIssueIds' => []])->assertJsonPath('data.revision', 2);
        $this->rpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => 1, 'name' => '舊表單'])->assertStatus(409);
        $this->rpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => 1])->assertStatus(409);
        $delete = ['id' => $board['id'], 'expectedRevision' => 2, 'requestId' => 'archive-board-once'];
        $this->rpc('archiveBoard', $delete)->assertOk(); $this->rpc('archiveBoard', $delete)->assertOk();
        $this->rpc('getWorkspace')->assertJsonCount(2, 'data.boards')->assertJsonCount(2, 'data.issues');
        $this->rpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => 3, 'name' => '復活'])->assertStatus(404);
    }

    public function test_board_creation_includes_new_card_atomically_and_does_not_snapshot_epic_children(): void
    {
        $this->register(); $project = $this->project();
        $epic = $this->issue($project, ['type' => 'epic']);
        $board = $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => 'Epic 計畫', 'selectedIssueIds' => [$epic['id']]])->assertOk()->json('data');
        $child = $this->issue($project, ['epicId' => $epic['id'], 'boardId' => $board['id']]);
        $boards = $this->rpc('getWorkspace')->json('data.boards');
        $stored = array_values(array_filter($boards, fn ($b) => $b['id'] === $board['id']))[0];
        $this->assertSame([$epic['id']], $stored['selectedIssueIds']);
        $this->assertSame(1, $stored['revision']);
        $input = ['projectId' => $project['id'], 'title' => '直接收錄', 'boardId' => $board['id'], 'requestId' => 'new-board-card'];
        $card = $this->rpc('createIssue', $input)->assertOk()->json('data');
        $this->rpc('createIssue', $input)->assertJsonPath('data.id', $card['id']);
        $boards = $this->rpc('getWorkspace')->json('data.boards');
        $stored = array_values(array_filter($boards, fn ($b) => $b['id'] === $board['id']))[0];
        $this->assertSame([$epic['id'], $card['id']], $stored['selectedIssueIds']);
        $this->assertSame(2, $stored['revision']);
        $other = $this->project('OTHER');
        $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => '錯誤看板', 'boardId' => $board['id']])->assertStatus(404);
        $this->rpc('getWorkspace')->assertJsonCount(3, 'data.issues');
    }

    public function test_boards_are_tenant_scoped_and_permissions_are_required_before_retries(): void
    {
        $owner = $this->register(); $project = $this->project();
        $board = $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => '私人空間內的看板'])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        $this->register('other-board@example.test');
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.boards');
        $this->rpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => 1, 'name' => '越權'])->assertStatus(404);
        $this->rpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => 1])->assertStatus(404);
        $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => '越權'])->assertStatus(404);
        // 有效 permission 才是入口, 即使 context 中保留 owner 身分也不能放行
        $context = ['workspace' => $owner['workspace'], 'user' => $owner['user'], 'role' => 'owner', 'permissions' => ['project.read']];
        $this->expectException(DomainError::class);
        $this->expectExceptionMessage('沒有執行此操作的權限');
        DB::transaction(fn () => (new WorkspaceService($context))->execute('createBoard', ['projectId' => $project['id'], 'name' => '不應建立', 'requestId' => 'denied']));
    }
}
