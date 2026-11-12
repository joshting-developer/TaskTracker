<?php

namespace Tests\Concerns;

use App\Domain\DomainError;
use App\Models\Issue;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 同一可信Store只重用識別碼，領域資料與授權不得重用過期快照。 */
trait RecordContextCases
{
    /** 對照查詢需限定實際參照，無關卡片成長不能擴大映射資料。 */
    public function test_record_context_limits_and_reuses_identity_queries(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Context', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Context'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Context', 'code' => 'CTX'])->assertOk()->json('data');
        $peer = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Peer'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Current', 'assignee' => $identity['user']['id'], 'relatedIssueIds' => [$peer['id']]])->assertOk()->json('data');
        $row = Issue::where('public_id', $peer['id'])->firstOrFail()->getAttributes();
        unset($row['id']);
        for ($i = 0; $i < 80; $i++) {
            DB::table('issues')->insert([...$row, 'public_id' => (string) Str::uuid(), 'identifier' => 'CTX-'.($i + 3), 'storage_position' => $i + 3]);
        }
        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $start = hrtime(true);
        for ($i = 0; $i < 8; $i++) {
            $value = $store->require('issues', $issue['id']);
            $this->assertSame($project['id'], $value['projectId']);
            $this->assertSame([$peer['id']], $value['relatedIssueIds']);
            $this->assertSame($identity['user']['id'], $value['assignee']);
        }
        $queries = DB::getQueryLog();
        $identityQueries = array_values(array_filter($queries, fn ($q) => preg_match('/^select ["`]?public_id["`]?, ["`]?id["`]? from/', $q['query'])
            || preg_match('/^select ["`]?user_id["`]?, ["`]?id["`]? from/', $q['query'])));
        fwrite(STDERR, 'TT225_METRICS '.json_encode(['driver' => DB::getDriverName(), 'reads' => 8, 'unrelatedIssues' => 80, 'queries' => count($queries), 'identityQueries' => count($identityQueries), 'sqlMs' => round(array_sum(array_column($queries, 'time')), 3), 'elapsedMs' => round((hrtime(true) - $start) / 1e6, 3)]).PHP_EOL);
        $this->assertLessThanOrEqual(3, count($identityQueries), '相同Store的穩定外鍵對照只需讀一次');
        foreach ($identityQueries as $query) {
            $this->assertMatchesRegularExpression('/\b(?:id|public_id|user_id)["`]? in \(/', $query['query'], '只查明確參照ID，不建立全租戶映射');
            $this->assertLessThanOrEqual(3, count($query['bindings']));
        }
        $this->assertSame('Current', $value['title']);
        DB::table('issues')->where('public_id', $issue['id'])->update(['title' => 'Fresh', 'revision' => 19]);
        $fresh = $store->require('issues', $issue['id']);
        $this->assertSame('Fresh', $fresh['title']);
        $this->assertSame(19, $fresh['revision']);
    }

    /** 成員欄位每次按指定帳號重讀，不把姓名、資格或停用狀態保留在ID快取。 */
    public function test_record_context_does_not_cache_member_or_cross_workspace_data(): void
    {
        $first = $this->postJson('/api/auth/register', ['name' => 'First', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'First'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'First', 'code' => 'FIRST'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'First'])->assertOk()->json('data');
        $store = (new RecordStore)->forWorkspace($first['workspace']['id']);
        $this->assertSame('First', $store->require('members', $first['user']['id'])['name']);
        DB::table('users')->where('id', $first['user']['id'])->update(['name' => 'Renamed']);
        DB::table('workspace_members')->where('user_id', $first['user']['id'])->update(['disabled_at' => now()]);
        $member = $store->require('members', $first['user']['id']);
        $this->assertSame('Renamed', $member['name']);
        $this->assertNotEmpty($member['disabledAt']);
        Auth::logout();
        $second = $this->postJson('/api/auth/register', ['name' => 'Second', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Second'])->assertOk()->json('data');
        $otherStore = $store->forWorkspace($second['workspace']['id']);
        $this->assertNull($otherStore->get('issues', $issue['id']));
        $this->assertNull($otherStore->get('members', $first['user']['id']));
        $this->assertSame('First', $store->require('issues', $issue['id'])['title']);
    }

    /** 大型子集合對照分批，不退化為每列查詢或全Workspace映射。 */
    public function test_record_context_batches_child_references_and_clears_after_failed_write(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Batch', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Batch'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Batch', 'code' => 'IDS'])->assertOk()->json('data');
        $source = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Source'])->assertOk()->json('data');
        $model = Issue::where('public_id', $source['id'])->firstOrFail();
        $row = $model->getAttributes();
        unset($row['id']);
        $expected = [];
        for ($i = 0; $i < 105; $i++) {
            $id = (string) Str::uuid();
            $expected[] = $id;
            $internal = DB::table('issues')->insertGetId([...$row, 'public_id' => $id, 'identifier' => 'IDS-'.($i + 2), 'storage_position' => $i + 2]);
            DB::table('issue_relations')->insert(['workspace_id' => $model->workspace_id, 'project_id' => $model->project_id, 'issue_id' => $model->id, 'related_issue_id' => $internal, 'position' => $i]);
        }
        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $actual = $store->require('issues', $source['id']);
        $this->assertSame($expected, $actual['relatedIssueIds']);
        $queries = array_filter(DB::getQueryLog(), fn ($q) => preg_match('/^select ["`]?public_id["`]?, ["`]?id["`]? from ["`]?issues/', $q['query']));
        $this->assertCount(2, $queries, '105參照只需100＋5兩批');
        foreach ($queries as $query) {
            $this->assertLessThanOrEqual(101, count($query['bindings']));
            $this->assertStringContainsString(' in (', $query['query']);
        }
        $invalid = [...$actual, 'id' => (string) Str::uuid(), 'identifier' => 'IDS-FAIL', 'title' => 'Rollback', 'relatedIssueIds' => [(string) Str::uuid()]];
        try {
            $store->put('issues', $invalid['id'], $invalid);
            $this->fail('不存在的外鍵必須拒絕');
        } catch (DomainError $error) {
            $this->assertStringContainsString('關聯資料不存在', $error->getMessage());
        }
        $this->assertNull($store->get('issues', $invalid['id']));
        DB::table('issues')->where('public_id', $source['id'])->update(['title' => 'Still fresh', 'revision' => 20]);
        $this->assertSame(20, $store->require('issues', $source['id'])['revision']);
        $this->assertSame($expected, $store->require('issues', $source['id'])['relatedIssueIds']);
    }
}
