<?php

namespace Tests\Concerns;

use App\Models\Issue;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 驗證關聯寫入僅讀取目標與必要子項，供SQLite與獨立MySQL共用。 */
trait IssueLinkQueryCases
{
    /**
     * 無關專案成長不增加建立卡片時的Issue hydration，且不讀全部工時。
     *
     * @return void
     */
    public function test_issue_link_validation_does_not_hydrate_unrelated_issues(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Links', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Links'])->assertOk();
        $project = $this->rpc('createProject', ['name' => 'Links', 'code' => 'LINKS'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk()->json('data');
        $source = $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Unrelated', 'description' => str_repeat('private ', 500)])->assertOk()->json('data');
        $hydrated = 0;
        Issue::retrieved(function () use (&$hydrated) {
            $hydrated++;
        });
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Before'])->assertOk();
        $before = $hydrated;
        $row = Issue::where('public_id', $source['id'])->firstOrFail()->getAttributes();
        unset($row['id']);
        for ($i = 0; $i < 120; $i++) {
            DB::table('issues')->insert([...$row, 'public_id' => (string) Str::uuid(), 'identifier' => 'OTHER-'.($i + 2), 'storage_position' => $i + 2]);
        }
        $hydrated = 0;
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'After'])->assertOk();
        $this->assertSame($before, $hydrated, '無關卡片不應增加驗證讀入的Model數');
        $this->assertLessThan(8, $hydrated);
        foreach (DB::getQueryLog() as $query) {
            if (str_starts_with(strtolower($query['query']), 'select') && str_contains($query['query'], 'issues')) {
                $this->assertStringNotContainsString('order by', strtolower($query['query']), '建立卡片不應列出整個Workspace');
            }
        }
    }

    /**
     * 父子同步、雙向關聯、型別與Epic結案規則不因限定查詢而放寬。
     *
     * @return void
     */
    public function test_issue_link_queries_preserve_relationship_rules(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Rules', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Rules'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Rules', 'code' => 'RULES'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk()->json('data');
        $make = fn (array $fields = []) => $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Card', ...$fields])->assertOk()->json('data');
        $first = $make(['type' => 'epic']);
        $second = $make(['type' => 'epic']);
        $parent = $make(['epicId' => $first['id']]);
        $child = $make(['parentIssueId' => $parent['id']]);
        $peer = $make();
        $foreign = $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Foreign'])->assertOk()->json('data');
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'parentIssueId' => $peer['id']])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $child['id'], 'expectedRevision' => 1, 'parentIssueId' => $child['id']])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'relatedIssueIds' => [$foreign['id']]])->assertStatus(400);
        $updated = $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'epicId' => $second['id'], 'relatedIssueIds' => [$peer['id']]])->assertOk()->json('data');
        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        $this->assertSame($second['id'], $store->require('issues', $child['id'])['epicId']);
        $this->assertSame([$parent['id']], $store->require('issues', $peer['id'])['relatedIssueIds']);
        $this->rpc('updateIssue', ['id' => $second['id'], 'expectedRevision' => 1, 'state' => 'closed'])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $second['id'], 'expectedRevision' => 1, 'type' => 'task'])->assertStatus(400);
        $this->rpc('logTime', ['issueId' => $peer['id'], 'workDate' => now('Asia/Taipei')->toDateString(), 'minutes' => 15])->assertOk();
        $this->rpc('updateIssue', ['id' => $peer['id'], 'expectedRevision' => $store->require('issues', $peer['id'])['revision'], 'type' => 'epic'])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => $updated['revision'], 'relatedIssueIds' => []])->assertOk();
        $this->assertSame([], $store->require('issues', $peer['id'])['relatedIssueIds']);
    }

    /**
     * 反向200筆容量以聚合驗證；既有連結可保留，超額／不存在不能部分寫入。
     *
     * @return void
     */
    public function test_issue_link_capacity_and_minimal_projection(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Capacity', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Capacity'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Capacity', 'code' => 'CAP'])->assertOk()->json('data');
        $target = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Target'])->assertOk()->json('data');
        $source = Issue::where('public_id', $target['id'])->firstOrFail();
        $row = $source->getAttributes();
        unset($row['id']);
        $firstId = '';
        for ($i = 0; $i < 200; $i++) {
            $id = (string) Str::uuid();
            $firstId = $firstId ?: $id;
            $internalId = DB::table('issues')->insertGetId([...$row, 'public_id' => $id, 'identifier' => 'CAP-'.($i + 2), 'storage_position' => $i + 2]);
            DB::table('issue_relations')->insert(['workspace_id' => $source->workspace_id, 'project_id' => $source->project_id, 'issue_id' => $source->id, 'related_issue_id' => $internalId, 'position' => $i]);
        }
        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $links = $store->issueLinks([$target['id']], $firstId);
        $this->assertSame(200, $links[$target['id']]->relatedCount);
        $this->assertTrue($links[$target['id']]->alreadyRelated);
        $this->assertCount(1, $links);
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('description', $query['query']);
            $this->assertStringNotContainsString('issue_comments', $query['query']);
            $this->assertStringNotContainsString('audit_events', $query['query']);
        }
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Overflow', 'relatedIssueIds' => [$target['id']]])->assertStatus(400);
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Missing', 'relatedIssueIds' => [(string) Str::uuid()]])->assertStatus(404);
        $this->assertSame(201, Issue::where('project_id', $source->project_id)->count());
        $this->rpc('updateIssue', ['id' => $firstId, 'expectedRevision' => 1, 'relatedIssueIds' => [$target['id']]])->assertOk();
        $this->assertSame(200, $store->issueLinks([$target['id']])[$target['id']]->relatedCount);
    }

    /**
     * 超過一批的子項仍全部同步，不更新同專案的無關卡片。
     *
     * @return void
     */
    public function test_issue_epic_propagation_crosses_bounded_batches(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Batch', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Batch'])->assertOk();
        $project = $this->rpc('createProject', ['name' => 'Batch', 'code' => 'BATCH'])->assertOk()->json('data');
        $epic = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Epic', 'type' => 'epic'])->assertOk()->json('data');
        $parent = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Parent'])->assertOk()->json('data');
        $source = Issue::where('public_id', $parent['id'])->firstOrFail();
        $row = $source->getAttributes();
        unset($row['id']);
        for ($i = 0; $i < 102; $i++) {
            DB::table('issues')->insert([...$row, 'public_id' => (string) Str::uuid(), 'parent_issue_id' => $source->id, 'identifier' => 'BATCH-'.($i + 3), 'storage_position' => $i + 3]);
        }
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'epicId' => $epic['id']])->assertOk();
        $epicId = Issue::where('public_id', $epic['id'])->value('id');
        $this->assertSame(102, Issue::where('parent_issue_id', $source->id)->where('epic_id', $epicId)->where('revision', 2)->count());
        $this->assertSame(1, Issue::where('public_id', $epic['id'])->value('revision'));
    }
}
