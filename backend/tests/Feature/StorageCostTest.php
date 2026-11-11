<?php

namespace Tests\Feature;

use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** 驗證資料量增加時，不以重寫舊子列或建立Session實作小型操作。 */
class StorageCostTest extends TestCase
{
    use RefreshDatabase;

    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    public function test_health_has_no_session_cookie_or_database_queries(): void
    {
        config(['session.driver' => 'database']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/health')->assertOk()->assertHeaderMissing('Set-Cookie');
        $this->assertSame([], DB::getQueryLog());
    }

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
        $this->rpc('updateBoard', ['id' => $board['id'], 'name' => 'Renamed', 'selectedIssueIds' => $board['selectedIssueIds'], 'expectedRevision' => $board['revision']])->assertOk();
        $this->assertSame($pivotIds, DB::table('board_issues')->pluck('id')->all());

        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        $issue = $issues[0];
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
        $this->assertCount(101, $changed['comments']);
        DB::flushQueryLog();
        $this->rpc('updateIssue', ['id' => $changed['id'], 'state' => 'inprogress', 'expectedRevision' => $changed['revision']])->assertOk();
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'issue_comments') && ! str_starts_with($q['query'], 'select')));
    }
}
