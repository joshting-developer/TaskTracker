<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Project;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\MySqlTestCase;
use Tests\Support\LegacyMemberTimeDetails;

/** 原生MySQL大量工時驗證SQL聚合與雙層分頁，不接觸業務資料。 */
class MySqlTimeReportTest extends MySqlTestCase
{
    use DatabaseTransactions;

    /** @return void */
    public function test_large_report_keeps_totals_bounded_details_and_query_plan(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'report', 'email' => Str::uuid().'@example.test', 'password' => 'report-native-test', 'workspaceName' => 'report'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'report', 'code' => 'REPORT', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $ids = [];
        for ($n = 0; $n < 30; $n++) {
            $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'card'.$n, 'type' => $n === 0 ? 'epic' : 'task', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
            $ids[] = Issue::where('public_id', $issue['id'])->firstOrFail()->id;
        }
        $model = Project::where('public_id', $project['id'])->firstOrFail();
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
        $rows = [];
        for ($n = 0; $n < 10000; $n++) {
            $rows[] = ['workspace_id' => $model->workspace_id, 'project_id' => $model->id, 'issue_id' => $ids[$n % 30], 'member_id' => $member->id, 'public_id' => (string) Str::uuid(), 'work_date' => now()->startOfYear()->addDays($n % 100)->toDateString(), 'minutes' => 1, 'note' => str_repeat('說明', 80), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('time_entries')->insert($chunk);
        }
        unset($rows);
        $query = ['startDate' => now()->startOfYear()->toDateString(), 'endDate' => now()->startOfYear()->addDays(99)->toDateString()];
        $samples = [];
        $queries = [];
        for ($run = 0; $run < 5; $run++) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $start = hrtime(true);
            $report = $this->postJson('/api/rpc/getTimeReport', $query)->assertOk()->json('data');
            $samples[] = (hrtime(true) - $start) / 1e6;
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $this->assertSame(10000, $report['summary']['totalMinutes']);
            $this->assertSame(10000, $report['summary']['entryCount']);
            $this->assertCount(100, $report['days']);
        }
        // 加資料筆數不增加PHP原始列；摘要SQL不投影說明／歷史。
        foreach ($queries as $sql) {
            if (str_contains($sql['query'], 'from `time_entries`')) {
                $this->assertStringContainsString('group by', $sql['query']);
                $this->assertStringNotContainsString('`time_entries`.`note`', $sql['query']);
            }
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $detailsQuery = [...$query, 'userId' => $identity['user']['id'], 'detailGroup' => 'issues'];
        $first = $this->postJson('/api/rpc/getTimeReport', $detailsQuery)->assertOk()->json('data');
        $this->assertSame(30, $first['details']['total']);
        $this->assertCount(20, $first['details']['rows']);
        $next = $this->postJson('/api/rpc/getTimeReport', [...$detailsQuery, 'detailPage' => 2])->assertOk()->json('data.details');
        $this->assertCount(10, $next['rows']);
        $this->assertSame([], array_intersect(array_column($first['details']['rows'], 'key'), array_column($next['rows'], 'key')));
        $key = $first['details']['rows'][0]['key'];
        $expanded = $this->postJson('/api/rpc/getTimeReport', [...$detailsQuery, 'entryGroupKey' => $key, 'entryPage' => 2])->assertOk()->json('data.details.expanded');
        $this->assertCount(20, $expanded['rows']);
        $this->assertSame(334, $expanded['total']);
        $detailSql = DB::getQueryLog();
        DB::disableQueryLog();
        $entryReads = array_values(array_filter($detailSql, fn ($q) => str_contains($q['query'], '`time_entries`.`note`') && ! str_contains($q['query'], 'count(*)')));
        $this->assertCount(1, $entryReads);
        $this->assertStringContainsString('limit 20 offset 20', $entryReads[0]['query']);
        $raw = DB::table('time_entries')->where('workspace_id', $model->workspace_id)->orderBy('id')->get();
        $issues = Issue::where('workspace_id', $model->workspace_id)->get()->keyBy('id');
        $oracleEntries = $raw->map(fn ($r) => ['id' => $r->public_id, 'issueId' => $issues[$r->issue_id]->public_id, 'projectId' => $project['id'], 'workDate' => $r->work_date, 'minutes' => $r->minutes, 'note' => $r->note])->all();
        $oracleIssues = $issues->mapWithKeys(fn ($i) => [$i->public_id => ['id' => $i->public_id, 'projectId' => $project['id'], 'identifier' => $i->identifier, 'title' => $i->title]])->all();
        foreach (['dates', 'issues'] as $group) {
            $input = [...$detailsQuery, 'detailGroup' => $group, 'detailPage' => 2];
            $actual = $this->postJson('/api/rpc/getTimeReport', $input)->assertOk()->json('data.details');
            $expected = (new LegacyMemberTimeDetails)->get($input, $oracleEntries, $oracleIssues, [$project['id'] => $project]);
            $this->assertDtoSame($expected, $actual);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $narrow = $this->postJson('/api/rpc/getTimeReport', [...$query, 'endDate' => $query['startDate']])->assertOk()->json('data');
        $narrowSql = array_values(array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'from `time_entries`')));
        DB::disableQueryLog();
        $indexScans = [];
        foreach ($narrowSql as $sql) {
            $tree = DB::selectOne('EXPLAIN ANALYZE '.$sql['query'], $sql['bindings']);
            foreach (explode("\n", array_values((array) $tree)[0]) as $line) {
                if (str_contains($line, ' on time_entries ') || str_contains($line, ' on time_entries(')) {
                    $indexScans[] = trim($line);
                }
            }
        }
        $this->assertSame(100, $narrow['summary']['totalMinutes']);
        $timeQueries = array_values(array_filter($queries, fn ($q) => str_contains($q['query'], 'from `time_entries`')));
        $plans = array_map(fn ($q) => DB::select('EXPLAIN '.$q['query'], $q['bindings']), $timeQueries);
        fwrite(STDERR, "\nREPORT_METRICS ".json_encode(['rows' => 10000, 'engine' => DB::selectOne('select version() as v')->v, 'samplesMs' => $samples, 'timeQueryCount' => count($timeQueries), 'narrowDateIndexScans' => $indexScans, 'plans' => $plans], JSON_UNESCAPED_UNICODE)."\n");
    }
}
