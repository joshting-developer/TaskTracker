<?php

namespace Tests\Concerns;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 同時在memory與隔離MySQL驗證讀取範圍 */
trait TimeReportReadCases
{
    /**
     * 大量無關專案不能使摘要夾帶metadata或歷史, 明細不重算配對
     *
     * @return void
     */
    public function test_report_options_and_details_are_separate_bounded_reads(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'analyst', 'email' => Str::uuid().'@example.test', 'password' => 'time-report-test', 'workspaceName' => 'analytics'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'focused', 'code' => 'FOCUS', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'focus card', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/rpc/logTime', ['issueId' => $issue['id'], 'workDate' => '2026-11-13', 'minutes' => 45, 'note' => 'private note', 'requestId' => (string) Str::uuid()])->assertOk();
        $filters = ['startDate' => '2026-11-01', 'endDate' => '2026-11-30', 'projectId' => $project['id']];
        $beforeGrowth = $this->getJson('/api/v1/time-report?'.http_build_query($filters))->assertOk()->json('data');
        $model = Project::where('public_id', $project['id'])->firstOrFail();
        $template = $model->getAttributes();
        unset($template['id']);
        for ($n = 0; $n < 105; $n++) {
            DB::table('projects')->insert([...$template, 'public_id' => (string) Str::uuid(), 'code' => 'UNRELATED'.$n, 'name' => 'unrelated'.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'description' => str_repeat('unused description', 100)]);
        }
        $filters = ['startDate' => '2026-11-01', 'endDate' => '2026-11-30', 'projectId' => $project['id']];
        $history = [];
        for ($n = 0; $n < 1000; $n++) {
            $history[] = ['workspace_id' => $model->workspace_id, 'entity_type' => 'projects', 'entity_id' => $model->id, 'stream' => 'history', 'position' => 10000 + $n, 'event_data' => json_encode(['synthetic' => str_repeat('unused history', 100)])];
        }
        foreach (array_chunk($history, 100) as $chunk) {
            DB::table('audit_events')->insert($chunk);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $report = $this->getJson('/api/v1/time-report?'.http_build_query($filters))->assertOk()->json('data');
        $this->assertSame($beforeGrowth, $report, '無關專案及歷史增長不能改變回應內容或大小');
        $this->assertLessThan(6000, strlen(json_encode($report)));
        $this->assertSame(45, $report['summary']['totalMinutes']);
        $this->assertCount(1, $report['projects']);
        $this->assertCount(30, $report['days']);
        $this->assertArrayNotHasKey('options', $report);
        $this->assertArrayNotHasKey('details', $report);
        $sql = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($sql as $query) {
            $this->assertStringNotContainsString('audit_events', $query['query']);
            $this->assertStringNotContainsString('description', $query['query']);
        }
        $first = $this->getJson('/api/v1/time-report/options/projects?perPage=20&search=unrelated')->assertOk()->json('data');
        $second = $this->getJson('/api/v1/time-report/options/projects?perPage=20&search=unrelated&page=2')->assertOk()->json('data');
        $this->assertSame(105, $first['total']);
        $this->assertCount(20, $first['items']);
        $this->assertSame(['id', 'name'], array_keys($first['items'][0]));
        $this->assertSame([], array_intersect(array_column($first['items'], 'id'), array_column($second['items'], 'id')));
        $this->getJson('/api/v1/time-report/options/projects?perPage=101')->assertStatus(400);
        $this->getJson('/api/v1/time-report/options/projects?search=%25')->assertOk()->assertJsonPath('data.total', 0);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $details = $this->getJson('/api/v1/time-report/details?'.http_build_query([...$filters, 'userId' => $identity['user']['id'], 'detailGroup' => 'dates', 'detailPage' => 1, 'entryGroupKey' => '2026-11-13|'.$issue['id']]))->assertOk()->json('data');
        $this->assertSame(45, $details['summary']['totalMinutes']);
        $this->assertSame('private note', $details['details']['expanded']['rows'][0]['note']);
        $this->assertSame(['range', 'summary', 'details'], array_keys($details));
        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('member_name', $query['query']);
            $this->assertStringNotContainsString('audit_events', $query['query']);
        }
        DB::disableQueryLog();
        $this->getJson('/api/v1/time-report/details?'.http_build_query($filters))->assertStatus(400);
        $this->getJson('/api/v1/time-report?'.http_build_query([...$filters, 'projectId' => 'outside-scope']))->assertStatus(404);
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
        $userTemplate = User::findOrFail($identity['user']['id'])->getAttributes();
        $memberTemplate = $member->getAttributes();
        unset($memberTemplate['id']);
        $memberIds = [];
        for ($n = 0; $n < 20; $n++) {
            $userId = (string) Str::uuid();
            DB::table('users')->insert([...$userTemplate, 'id' => $userId, 'name' => 'synthetic'.$n, 'email' => $userId.'@example.test']);
            $memberIds[] = DB::table('workspace_members')->insertGetId([...$memberTemplate, 'user_id' => $userId, 'role' => 'user']);
        }
        $issueTemplate = DB::table('issues')->where('public_id', $issue['id'])->first();
        $issueTemplate = (array) $issueTemplate;
        unset($issueTemplate['id']);
        $statusTemplate = (array) DB::table('project_statuses')->where('id', $issueTemplate['state_id'])->first();
        unset($statusTemplate['id']);
        $entries = [];
        foreach (DB::table('projects')->where('workspace_id', $model->workspace_id)->where('name', 'like', 'unrelated%')->pluck('id') as $projectKey) {
            $stateKey = DB::table('project_statuses')->insertGetId([...$statusTemplate, 'project_id' => $projectKey]);
            $issueKey = DB::table('issues')->insertGetId([...$issueTemplate, 'state_id' => $stateKey, 'project_id' => $projectKey, 'public_id' => (string) Str::uuid()]);
            foreach ($memberIds as $memberKey) {
                $entries[] = ['workspace_id' => $model->workspace_id, 'project_id' => $projectKey, 'issue_id' => $issueKey, 'member_id' => $memberKey, 'public_id' => (string) Str::uuid(), 'work_date' => '2026-11-13', 'minutes' => 1, 'note' => '', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        foreach (array_chunk($entries, 200) as $chunk) {
            DB::table('time_entries')->insert($chunk);
        }
        $this->getJson('/api/v1/time-report?startDate=2026-11-13&endDate=2026-11-13')->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->getJson('/api/v1/time-report?'.http_build_query($filters))->assertOk()->assertJsonPath('data.summary.totalMinutes', 45);

        $member->update(['role' => 'user']);
        $this->actingAs(User::findOrFail($identity['user']['id']));
        $this->getJson('/api/v1/time-report?'.http_build_query($filters))->assertStatus(403);
        $this->getJson('/api/v1/time-report/options/members')->assertStatus(403);
    }
}
