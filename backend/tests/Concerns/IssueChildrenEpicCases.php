<?php

namespace Tests\Concerns;

use App\Data\IssueChildrenScope;
use App\Domain\DomainError;
use App\Models\Issue;
use App\Repositories\IssueChildrenRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 驗證子任務批次寫入、歷史保留與同交易回滾 */
trait IssueChildrenEpicCases
{
    /**
     * 建立限定租戶的父項、Epic與大量活動子卡
     *
     * @param  int  $count  子卡數
     * @param  int  $historyCount  每卡舊歷史筆數
     * @return array 測試識別碼與原始活動
     */
    private function childrenFixture(int $count, int $historyCount = 60): array
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Batch tester', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-children-test', 'workspaceName' => 'Children'])->assertOk()->json('data');
        $rpc = fn ($operation, $input) => $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input])->assertOk()->json('data');
        $project = $rpc('createProject', ['name' => 'Children', 'code' => 'CHILD']);
        $epic = $rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Target', 'type' => 'epic']);
        $parent = $rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Parent']);
        $model = Issue::where('public_id', $parent['id'])->firstOrFail();
        $template = $model->getAttributes();
        unset($template['id']);
        $ids = [];
        $events = [];
        $comments = [];
        for ($n = 0; $n < $count; $n++) {
            $id = DB::table('issues')->insertGetId([...$template, 'public_id' => (string) Str::uuid(), 'identifier' => 'CHILD-'.($n + 3), 'parent_issue_id' => $model->id, 'description' => str_repeat('unused child description ', 100)]);
            $ids[] = $id;
            for ($position = 0; $position < $historyCount; $position++) {
                $events[] = ['workspace_id' => $model->workspace_id, 'entity_type' => 'issues', 'entity_id' => $id, 'stream' => 'history', 'position' => $position,
                    'event_data' => json_encode(['id' => 'old'.$position, 'kind' => 'updated', 'actor' => 'historical', 'at' => '2026-11-01T00:00:00Z', 'changes' => [], 'note' => str_repeat('old history ', 40)])];
            }
            for ($position = 0; $position < 20; $position++) {
                $comments[] = ['issue_id' => $id, 'public_id' => 'comment'.$position, 'position' => $position, 'author' => 'historical', 'content' => str_repeat('old comment ', 40)];
            }
        }
        foreach (array_chunk($events, 200) as $rows) {
            DB::table('audit_events')->insert($rows);
        }
        foreach (array_chunk($comments, 200) as $rows) {
            DB::table('issue_comments')->insert($rows);
        }

        return compact('identity', 'project', 'epic', 'parent', 'model', 'ids', 'historyCount');
    }

    /**
     * 1／20／100子卡不讀取舊活動JSON, 逐卡版本與新增歷史仍正確
     *
     * @return void
     */
    public function test_children_epic_update_is_bounded_and_preserves_history_and_replay(): void
    {
        foreach ([1, 20, 100] as $count) {
            $fixture = $this->childrenFixture($count);
            extract($fixture);
            $unrelated = DB::table('issues')->insertGetId([...array_diff_key($model->getAttributes(), ['id' => true]), 'public_id' => (string) Str::uuid(), 'identifier' => 'OTHER-PARENT', 'parent_issue_id' => null]);
            $before = DB::table('audit_events')->whereIn('entity_id', $ids)->where('entity_type', 'issues')->orderBy('id')->pluck('event_data')->all();
            $repo = app(IssueChildrenRepository::class);
            $scope = $repo->scope($identity['workspace']['id'], $project['id'], $parent['id'], $epic['id']);
            foreach ($repo->batches($scope) as $batch) {
                $this->assertLessThanOrEqual(100, count($batch));
                $this->assertSame(['id', 'public_id', 'revision', 'epic_public_id', 'history_position'], array_keys((array) $batch[0]));
            }
            $payload = ['id' => $parent['id'], 'epicId' => $epic['id'], 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()];
            DB::flushQueryLog();
            DB::enableQueryLog();
            $result = $this->postJson('/api/rpc/updateIssue', $payload)->assertOk()->json('data');
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            foreach ($queries as $query) {
                if (str_starts_with($query['query'], 'select') && (str_contains($query['query'], 'issue_comments') || str_contains($query['query'], 'event_data'))) {
                    $this->assertSame([], array_values(array_intersect($ids, $query['bindings'])), '不得讀取子卡留言與歷史JSON');
                }
            }
            if ($count === 100 && DB::getDriverName() === 'mysql') {
                $minimal = array_values(array_filter($queries, fn ($query) => str_contains($query['query'], 'as `history_position`')))[0];
                $plan = DB::select('EXPLAIN ANALYZE '.$minimal['query'], $minimal['bindings'])[0]->EXPLAIN;
                $this->assertStringContainsString('(reverse)', $plan);
                $this->assertStringNotContainsString('Sort: audit_events', $plan);
                $this->assertStringContainsString('rows=1 loops=100', $plan);
            }
            $this->assertSame($count, DB::table('issues')->whereIn('id', $ids)->where('epic_id', $scope->epicId)->where('revision', 2)->count());
            $this->assertSame($count * $historyCount, count($before));
            $after = DB::table('audit_events')->whereIn('entity_id', $ids)->where('entity_type', 'issues')->where('position', '<', $historyCount)->orderBy('id')->pluck('event_data')->all();
            $this->assertSame($before, $after);
            $events = DB::table('audit_events')->whereIn('entity_id', $ids)->where('entity_type', 'issues')->where('position', $historyCount)->pluck('event_data');
            $this->assertCount($count, $events);
            foreach ($events as $event) {
                $event = json_decode($event, true);
                $this->assertSame('Batch tester', $event['actor']);
                $this->assertCount(1, $event['changes']);
                $this->assertSame('epicId', $event['changes'][0]['field']);
                $this->assertSame('', $event['changes'][0]['before']);
                $this->assertSame($epic['id'], $event['changes'][0]['after']);
            }
            $this->assertSame($count * 20, DB::table('issue_comments')->whereIn('issue_id', $ids)->count());
            $this->assertSame(1, DB::table('issues')->where('id', $unrelated)->value('revision'));
            $this->assertNull(DB::table('issues')->where('id', $unrelated)->value('epic_id'));
            $replay = $this->postJson('/api/rpc/updateIssue', $payload)->assertOk()->json('data');
            $this->assertSame($result['revision'], $replay['revision']);
            $this->assertSame($count, DB::table('issues')->whereIn('id', $ids)->where('revision', 2)->count());
            $this->assertSame($count, DB::table('audit_events')->whereIn('entity_id', $ids)->where('position', $historyCount)->count());
            $this->postJson('/api/rpc/updateIssue', [...$payload, 'requestId' => (string) Str::uuid(), 'expectedRevision' => 2])->assertOk()->assertJsonPath('data.revision', 2);
            $this->assertSame($count, DB::table('issues')->whereIn('id', $ids)->where('revision', 2)->count());
            $this->postJson('/api/rpc/updateIssue', [...$payload, 'requestId' => (string) Str::uuid(), 'expectedRevision' => 2, 'epicId' => ''])->assertOk()->assertJsonPath('data.revision', 3);
            $this->assertSame($count, DB::table('issues')->whereIn('id', $ids)->whereNull('epic_id')->where('revision', 3)->count());
            $cleared = json_decode(DB::table('audit_events')->where('entity_type', 'issues')->where('entity_id', $ids[0])->where('position', $historyCount + 1)->value('event_data'), true);
            $this->assertSame($epic['id'], $cleared['changes'][0]['before']);
            $this->assertSame('', $cleared['changes'][0]['after']);
        }
    }

    /**
     * 第二批失敗必須回滾父項、第一批子卡及全部新增活動
     *
     * @return void
     */
    public function test_second_batch_failure_rolls_back_the_whole_operation(): void
    {
        $fault = new class extends IssueChildrenRepository
        {
            public int $calls = 0;

            public function apply(IssueChildrenScope $scope, array $changes, string $at): int
            {
                $count = parent::apply($scope, $changes, $at);
                if (++$this->calls === 2) {
                    DomainError::fail('INVALID_INPUT', 'synthetic second batch failure');
                }

                return $count;
            }
        };
        $this->app->instance(IssueChildrenRepository::class, $fault);
        $fixture = $this->childrenFixture(101, 2);
        extract($fixture);
        $this->postJson('/api/rpc/updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'epicId' => $epic['id'], 'requestId' => (string) Str::uuid()])->assertStatus(400)->assertJsonPath('error.message', 'synthetic second batch failure');
        $this->assertSame(2, $fault->calls);
        $this->assertSame(1, Issue::findOrFail($model->id)->revision);
        $this->assertSame(101, DB::table('issues')->whereIn('id', $ids)->whereNull('epic_id')->where('revision', 1)->count());
        $this->assertSame(0, DB::table('audit_events')->whereIn('entity_id', $ids)->where('position', 2)->count());
    }

    /**
     * 子卡版本已變時不能留下部分批次, 也不能追加成功歷史
     *
     * @return void
     */
    public function test_child_revision_conflict_rolls_back_parent_and_children(): void
    {
        $fault = new class extends IssueChildrenRepository
        {
            public function apply(IssueChildrenScope $scope, array $changes, string $at): int
            {
                DB::table('issues')->where('id', $changes[0]['id'])->increment('revision');

                return parent::apply($scope, $changes, $at);
            }
        };
        $this->app->instance(IssueChildrenRepository::class, $fault);
        $fixture = $this->childrenFixture(20, 2);
        extract($fixture);
        $this->postJson('/api/rpc/updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'epicId' => $epic['id'], 'requestId' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');
        $this->assertSame(1, Issue::findOrFail($model->id)->revision);
        $this->assertSame(20, DB::table('issues')->whereIn('id', $ids)->whereNull('epic_id')->where('revision', 1)->count());
        $this->assertSame(0, DB::table('audit_events')->whereIn('entity_id', $ids)->where('position', 2)->count());
    }
}
