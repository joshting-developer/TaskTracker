<?php

namespace Tests\Feature;

use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\StoredWriteResults;
use Tests\TestCase;

/** 驗證資料量增加時，不以重寫舊子列或建立Session實作小型操作。 */
class StorageCostTest extends TestCase
{
    use RefreshDatabase, StoredWriteResults;

    /**
     * 經正式Session RPC呼叫指定操作，為測試輸入補入可覆寫的requestId。
     *
     * @param  string  $operation  已註冊的RPC操作
     * @param  array<string,mixed>  $input  測試輸入，可明確指定原requestId
     * @return TestResponse 真實HTTP測試回應
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 驗證存活探針不建立Session Cookie，也不查詢資料庫。
     *
     * @return void
     */
    public function test_health_has_no_session_cookie_or_database_queries(): void
    {
        config(['session.driver' => 'database']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/health')->assertOk()->assertHeaderMissing('Set-Cookie');
        $this->assertSame([], DB::getQueryLog());
    }

    /**
     * 驗證看板收錄以批次查詢驗證、未變更關聯列不重寫，留言僅追加保存。
     *
     * @return void
     */
    public function test_board_validation_is_batched_and_comments_are_append_only(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Cost', 'email' => 'cost@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Cost'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Cost', 'code' => 'COST'])->assertOk()->json('data');
        $issues = [];
        for ($index = 0; $index < 12; $index++) {
            $issues[] = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Issue '.$index])->assertOk()->json('data');
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $board = $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => 'Batch', 'selectedIssueIds' => array_column($issues, 'id')])->assertOk()->json('data');
        $queries = DB::getQueryLog();
        $this->assertCount(0, array_filter($queries, fn ($q) => str_contains($q['query'], 'issue_comments')));
        $referenceReads = array_filter($queries, fn ($q) => str_contains($q['query'], 'project_public_id') && str_starts_with($q['query'], 'select'));
        $this->assertCount(1, $referenceReads);
        $pivotIds = DB::table('board_issues')->pluck('id')->all();
        $this->rpc('updateBoard', ['id' => $board['id'], 'name' => 'Renamed', 'selectedIssueIds' => array_column($issues, 'id'), 'expectedRevision' => $board['revision']])->assertOk();
        $this->assertSame($pivotIds, DB::table('board_issues')->pluck('id')->all());

        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        $issue = $this->storedIssue($issues[0]);
        $issue['comments'] = [];
        for ($index = 0; $index < 100; $index++) {
            $issue['comments'][] = ['id' => (string) Str::uuid(), 'content' => 'Old '.$index, 'author' => 'Cost', 'createdAt' => now()->toISOString()];
        }
        $store->put('issues', $issue['id'], $issue);
        $ids = DB::table('issue_comments')->orderBy('id')->pluck('id')->all();
        DB::flushQueryLog();
        $changed = $this->rpc('addComment', ['id' => $issue['id'], 'content' => 'New', 'expectedRevision' => $issue['revision']])->assertOk()->json('data');
        $writes = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'issue_comments') && ! str_starts_with($q['query'], 'select'));
        $this->assertCount(1, $writes);
        $this->assertSame($ids, DB::table('issue_comments')->orderBy('id')->limit(100)->pluck('id')->all());
        $this->assertCount(101, $this->storedIssue($changed)['comments']);
        DB::flushQueryLog();
        $this->rpc('updateIssue', ['id' => $changed['id'], 'state' => 'inprogress', 'expectedRevision' => $changed['revision']])->assertOk();
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'issue_comments') && ! str_starts_with($q['query'], 'select')));
    }
}
