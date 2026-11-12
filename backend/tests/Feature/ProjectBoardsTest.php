<?php

namespace Tests\Feature;

use App\Domain\DomainError;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** 驗證專案看板收錄、Epic動態展開、原子建卡與授權重播。 */
class ProjectBoardsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 透過正式註冊入口建立測試帳號與Workspace身分。
     *
     * @param  string  $email  此測試帳號Email
     * @return array 帳號、Workspace與有效權限
     */
    private function register(string $email = 'boards@example.test'): array
    {
        return $this->postJson('/api/auth/register', ['name' => 'Board user', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => '團隊'])->assertOk()->json('data');
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
     * 建立看板測試專案，回傳有界識別與版本結果。
     *
     * @param  string  $code  此測試專案的唯一代碼
     * @return array{id:string,revision:int} 專案寫入結果
     */
    private function project(string $code = 'BOARD'): array
    {
        return $this->rpc('createProject', ['name' => '專案', 'code' => $code])->assertOk()->json('data');
    }

    /**
     * 在指定測試專案建立卡片，回傳有界寫入結果。
     *
     * @param  array  $project  至少含id的測試專案
     * @param  array<string,mixed>  $fields  覆寫測試卡片欄位
     * @return array{id:string,projectId:string,identifier:string,revision:int} 卡片寫入結果
     */
    private function issue(array $project, array $fields = []): array
    {
        return $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '卡片', ...$fields])->assertOk()->json('data');
    }

    /**
     * 驗證Default固定看板、自訂收錄合法性、編輯revision與requestId重播。
     *
     * @return void
     */
    public function test_default_boards_and_custom_board_validation_revisions_and_retries(): void
    {
        $this->register();
        $project = $this->project();
        $other = $this->project('OTHER');
        $task = $this->issue($project);
        $external = $this->issue($other);
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
        $this->rpc('archiveBoard', $delete)->assertOk();
        $this->rpc('archiveBoard', $delete)->assertOk();
        $this->rpc('getWorkspace')->assertJsonCount(2, 'data.boards')->assertJsonCount(2, 'data.issues');
        $this->rpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => 3, 'name' => '復活'])->assertStatus(404);
    }

    /**
     * 驗證建卡與自訂看板收錄同交易完成，Epic子卡動態展開而非複製快照。
     *
     * @return void
     */
    public function test_board_creation_includes_new_card_atomically_and_does_not_snapshot_epic_children(): void
    {
        $this->register();
        $project = $this->project();
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

    /**
     * 驗證看板依租戶隔離，重試已完成操作前仍檢查目前權限。
     *
     * @return void
     */
    public function test_boards_are_tenant_scoped_and_permissions_are_required_before_retries(): void
    {
        $owner = $this->register();
        $project = $this->project();
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
        DB::transaction(fn () => app(WorkspaceService::class)->execute($context, 'createBoard', ['projectId' => $project['id'], 'name' => '不應建立', 'requestId' => 'denied']));
    }
}
