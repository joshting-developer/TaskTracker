<?php

namespace Tests\Concerns;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 活動總數只讀聚合, 真MySQL與快速隔離共用 */
trait IssueActivityCountCases
{
    /**
     * 超過20筆仍是真實總數, 不附內容；卡片讀者可看其他人的單卡工時但不能改
     *
     * @return void
     */
    public function test_activity_counts_are_scoped_lazy_and_card_time_is_shared(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Counter', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-activity-counts', 'workspaceName' => 'Counts'])->assertOk()->json('data');
        $rpc = fn ($op, $input) => $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input])->assertOk()->json('data');
        $project = $rpc('createProject', ['name' => 'Counts', 'code' => 'CNT']);
        $card = $rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Counted']);
        $model = Issue::where('public_id', $card['id'])->sole();
        for ($n = 0; $n < 21; $n++) {
            DB::table('issue_comments')->insert(['issue_id' => $model->id, 'public_id' => (string) Str::uuid(), 'content' => 'activity-sentinel-comment', 'author' => 'Counter', 'created_at' => now(), 'position' => $n]);
        }
        for ($n = 1; $n <= 20; $n++) {
            DB::table('audit_events')->insert(['workspace_id' => $model->workspace_id, 'entity_type' => 'issues', 'entity_id' => $model->id, 'stream' => 'history', 'position' => $n, 'event_data' => json_encode(['kind' => 'updated', 'actor' => 'activity-sentinel-history', 'at' => '2026-11-13T00:00:00Z', 'changes' => []])]);
        }
        $time = $rpc('logTime', ['issueId' => $card['id'], 'workDate' => '2026-11-13', 'minutes' => 15, 'note' => 'activity-sentinel-time']);
        $void = $rpc('logTime', ['issueId' => $card['id'], 'workDate' => '2026-11-13', 'minutes' => 5, 'note' => 'voided']);
        $rpc('voidTime', ['id' => $void['id'], 'expectedRevision' => $void['revision']]);
        $root = '/api/v1/projects/'.$project['id'].'/issues/'.$card['id'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->getJson($root)->assertOk()->assertJsonPath('data.activityCounts', ['comments' => 21, 'history' => 21, 'time' => 1]);
            $this->assertStringNotContainsString('activity-sentinel', $response->getContent());
            foreach (['comments', 'history', 'timeEntries'] as $field) {
                $this->assertArrayNotHasKey($field, $response->json('data'));
            }
            foreach (DB::getQueryLog() as $query) {
                if (str_contains($query['query'], 'issue_comments') || str_contains($query['query'], 'audit_events')) {
                    $this->assertStringContainsString('COUNT(*)', $query['query']);
                    $this->assertStringNotContainsString('event_data', $query['query']);
                    $this->assertStringNotContainsString('content', $query['query']);
                }
            }
        } finally {
            DB::disableQueryLog();
        }
        $this->getJson($root.'/comments')->assertOk()->assertJsonPath('data.total', 21)->assertJsonCount(20, 'data.items');
        $this->getJson($root.'/history')->assertOk()->assertJsonPath('data.total', 21)->assertJsonCount(20, 'data.items');
        $other = $rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Other']);
        $otherKey = Issue::where('public_id', $other['id'])->value('id');
        for ($n = 0; $n < 30; $n++) {
            DB::table('issue_comments')->insert(['issue_id' => $otherKey, 'public_id' => (string) Str::uuid(), 'content' => 'unrelated', 'author' => 'Other', 'position' => $n]);
        }
        $this->assertSame($response->getContent(), $this->getJson($root)->assertOk()->getContent());
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'counts', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $this->getJson('/api/public/v1/projects/'.$project['id'].'/issues/'.$card['id'], ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.activityCounts.comments', 21);
        $reader = User::factory()->create(['id' => (string) Str::uuid(), 'name' => 'Reader']);
        DB::table('workspace_members')->insert(['workspace_id' => $model->workspace_id, 'user_id' => $reader->id, 'role' => 'user']);
        $rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'roleId' => 'reader', 'expectedRevision' => 0]);
        $this->actingAs($reader)->withSession(['workspace_id' => $identity['workspace']['id']]);
        $this->getJson($root)->assertOk()->assertJsonPath('data.activityCounts.time', 1)->assertJsonPath('data.spentMinutes', 15);
        $this->getJson($root.'/time')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.note', 'activity-sentinel-time')->assertJsonPath('data.items.0.userId', $identity['user']['id']);
        $this->postJson('/api/rpc/updateTime', ['id' => $time['id'], 'expectedRevision' => $time['revision'], 'minutes' => 20, 'note' => 'not mine', 'requestId' => (string) Str::uuid()])->assertStatus(403);
        $this->getJson('/api/v1/projects/'.$project['id'].'/time-calendar?month=2026-11')->assertStatus(403);
        $this->getJson('/api/v1/time-report?view=members&startDate=2026-11-01&endDate=2026-11-30')->assertStatus(403);
        $this->actingAs(User::findOrFail($identity['user']['id']));
        $rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'roleId' => 'developer', 'expectedRevision' => 1]);
        $this->actingAs($reader);
        $this->postJson('/api/rpc/updateTime', ['id' => $time['id'], 'expectedRevision' => $time['revision'], 'minutes' => 20, 'note' => 'not mine', 'requestId' => (string) Str::uuid()])->assertStatus(403);
        $this->postJson('/api/rpc/voidTime', ['id' => $time['id'], 'expectedRevision' => $time['revision'], 'requestId' => (string) Str::uuid()])->assertStatus(403);
        DB::table('project_members')->where('project_id', Project::where('public_id', $project['id'])->value('id'))->update(['removed_at' => now()]);
        $this->getJson($root)->assertStatus(404);
        $this->getJson($root.'/time')->assertStatus(404);
    }
}
