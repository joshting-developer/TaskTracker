<?php

namespace Tests\Feature;

use App\Domain\Fields;
use App\Models\Issue;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** 看板查詢的Project範圍、Epic動態收錄、投影與有界分頁。 */
class BoardReadApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 透過真實寫入建立隔離fixture，保留服務驗證。
     *
     * @param  string  $operation  操作名稱
     * @param  array<string,mixed>  $input  fixture輸入
     * @return TestResponse HTTP回應
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 確認摘要不讀活動、Epic展開不重複、分頁不漏卡及撤權即生效。
     *
     * @return void
     */
    public function test_board_projection_scope_pagination_and_dynamic_epic_membership(): void
    {
        $this->getJson('/api/v1/projects/missing/boards')->assertStatus(401);
        $actor = $this->postJson('/api/auth/register', ['name' => 'Board', 'email' => 'board-query@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Boards'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'First', 'code' => 'FIRST'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk()->json('data');
        $epic = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Epic', 'type' => 'epic'])->assertOk()->json('data');
        $child = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Child', 'epicId' => $epic['id']])->assertOk()->json('data');
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Excluded'])->assertOk();
        $board = $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => 'Delivery', 'selectedIssueIds' => [$epic['id'], $child['id']]])->assertOk()->json('data');
        $foreign = $this->rpc('createBoard', ['projectId' => $other['id'], 'name' => 'Other', 'selectedIssueIds' => []])->assertOk()->json('data');
        $path = '/api/v1/projects/'.$project['id'].'/boards';
        DB::enableQueryLog();
        DB::flushQueryLog();
        $data = $this->getJson($path)->assertOk()->assertJsonPath('data.defaultBoard.issueCount', 3)->assertJsonPath('data.items.0.issueCount', 2)->json('data');
        $this->assertArrayNotHasKey('selectedIssueIds', $data['items'][0]);
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'issue_comments') || str_contains($q['query'], 'audit_events')));
        $this->getJson($path.'?perPage=101')->assertStatus(400);
        $this->getJson($path.'/'.$foreign['id'])->assertStatus(404);
        $this->getJson($path, ['X-Workspace-ID' => 'other'])->assertStatus(409);
        $this->getJson($path.'/'.$board['id'])->assertOk()->assertJsonCount(2, 'data.selectedIssueIds');
        $this->getJson($path.'/'.$board['id'].'/issues')->assertOk()->assertJsonPath('data.total', 2);
        $source = Issue::where('public_id', $child['id'])->firstOrFail();
        for ($i = 0; $i < 105; $i++) {
            $copy = $source->replicate();
            $copy->public_id = (string) Str::uuid();
            $copy->identifier = 'FIRST-X'.$i;
            $copy->save();
        }
        $page = $this->getJson($path.'/'.$board['id'].'/issues?perPage=100')->assertOk()->assertJsonPath('data.total', 107)->assertJsonCount(100, 'data.items')->json('data');
        $next = $this->getJson($path.'/'.$board['id'].'/issues?perPage=100&page=2')->assertOk()->assertJsonCount(7, 'data.items')->json('data');
        $this->assertSame([], array_intersect(array_column($page['items'], 'id'), array_column($next['items'], 'id')));
        $childrenPath = '/api/v1/projects/'.$project['id'].'/issues/'.$epic['id'].'/children';
        $this->getJson($childrenPath.'?perPage=100')->assertOk()->assertJsonPath('data.total', 106)->assertJsonCount(100, 'data.items');
        $this->getJson($childrenPath.'?perPage=100&page=2')->assertOk()->assertJsonCount(6, 'data.items');
        $optionsPath = '/api/v1/projects/'.$project['id'].'/issue-options';
        $this->getJson($optionsPath.'?kind=epic')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $epic['id']);
        $candidate = $this->getJson($optionsPath.'?search=FIRST-X99&perPage=30')->assertOk()->assertJsonPath('data.total', 1)->json('data.items.0');
        $this->assertSame('FIRST-X99', $candidate['identifier']);
        foreach (['description', 'comments', 'history', 'spentMinutes', 'estimateMinutes', 'revision', 'assigneeName'] as $field) {
            $this->assertArrayNotHasKey($field, $candidate);
        }
        $this->getJson($optionsPath.'?kind=anything')->assertStatus(400);
        $this->getJson($optionsPath.'?perPage=101')->assertStatus(400);
        $this->getJson($optionsPath.'?kind=epic&exclude='.$epic['id'])->assertOk()->assertJsonPath('data.total', 0);
        foreach ($page['items'] as $item) {
            foreach (['description', 'history', 'comments', 'legacy_attributes'] as $field) {
                $this->assertArrayNotHasKey($field, $item);
            }
        }
        $summary = $this->getJson($path)->assertOk()->assertJsonPath('data.items.0.issueCount', 107)->json('data');
        $this->assertSame(array_keys($data['items'][0]), array_keys($summary['items'][0]));
        $this->assertLessThan(strlen(json_encode($data)) + 30, strlen(json_encode($summary)));
        Issue::where('project_id', $source->project_id)->update(['description' => str_repeat('large-body', 500)]);
        $this->assertSame($summary, $this->getJson($path)->assertOk()->json('data'));
        $this->rpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision']])->assertOk();
        $this->getJson($path.'/'.$board['id'].'/issues')->assertStatus(404);
        WorkspaceMember::where('user_id', $actor['user']['id'])->update(['role' => 'user']);
        $this->getJson($path.'/default/issues')->assertStatus(404);
    }

    /**
     * TT-248：已完成數只計狀態type＝closed，與總數同一收錄／Epic展開／去重條件，Default與單一看板一致。
     *
     * @return void
     */
    public function test_board_summaries_count_closed_type_with_epic_expansion(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Done', 'email' => 'board-done@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Done'])->assertOk();
        $project = $this->rpc('createProject', ['name' => 'Done', 'code' => 'DONE'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Noise', 'code' => 'NOISE'])->assertOk()->json('data');
        $statuses = [...Fields::STATES, ['id' => 'done', 'label' => '完成', 'color' => '#71a2d5']];
        $this->rpc('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => 1, 'statuses' => $statuses])->assertOk();
        $create = fn (string $projectId, array $input) => $this->rpc('createIssue', ['projectId' => $projectId, ...$input])->assertOk()->json('data');
        $epic = $create($project['id'], ['title' => 'Epic', 'type' => 'epic']);
        $closedChild = $create($project['id'], ['title' => 'Closed child', 'epicId' => $epic['id'], 'state' => 'closed']);
        $create($project['id'], ['title' => 'Open child', 'epicId' => $epic['id']]);
        $create($project['id'], ['title' => '顯示名稱完成但非closed', 'state' => 'done']);
        $create($project['id'], ['title' => 'Closed outside board', 'state' => 'closed']);
        $create($other['id'], ['title' => 'Other closed', 'state' => 'closed']);
        $board = $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => 'Epic board', 'selectedIssueIds' => [$epic['id'], $closedChild['id']]])->assertOk()->json('data');
        $path = '/api/v1/projects/'.$project['id'].'/boards';

        $data = $this->getJson($path)->assertOk()->json('data');
        $this->assertSame([5, 2], [$data['defaultBoard']['issueCount'], $data['defaultBoard']['closedCount']]);
        $this->assertSame([3, 1], [$data['items'][0]['issueCount'], $data['items'][0]['closedCount']]);
        $show = $this->getJson($path.'/'.$board['id'])->assertOk()->json('data');
        $this->assertSame([3, 1], [$show['issueCount'], $show['closedCount']]);
        $this->getJson($path.'/default')->assertOk()->assertJsonPath('data.closedCount', 2);
        $this->getJson('/api/v1/projects/'.$other['id'].'/boards')->assertOk()->assertJsonPath('data.defaultBoard.closedCount', 1);
    }
}
