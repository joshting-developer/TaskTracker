<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\ProjectMember;
use App\Models\ProjectStatus;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** 用隔離MySQL驗證時程區間重疊、單端日期、未排程計數、分頁與撤權。 */
class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * includeClosed 與上限都限制在已授權單一 Project。
     *
     * @return void
     */
    public function test_closed_filter_limit_and_project_scope(): void
    {
        [, $project] = $this->fixture();
        $other = $this->postJson('/api/rpc/createProject', ['name' => 'Other', 'code' => 'OTHER', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $foreign = $this->issue($other, ['start_date' => '2026-11-25']);
        $open = $this->issue($project, ['start_date' => '2026-11-25']);
        $closed = $this->issue($project, ['start_date' => '2026-11-25']);
        $closedState = ProjectStatus::where('project_id', $closed->project_id)->where('code', 'closed')->firstOrFail();
        $closed->update(['state_id' => $closedState->id]);
        $url = '/api/v1/projects/'.$project['id'].'/schedule?from=2026-11-23&to=2026-11-29';
        $this->assertSame([$open->public_id], array_column($this->getJson($url)->assertOk()->json('data.items'), 'id'));
        $included = $this->getJson($url.'&includeClosed=1')->assertOk()->json('data.items');
        $this->assertCount(2, $included);
        $this->assertNotContains($foreign->public_id, array_column($included, 'id'));
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $row = $open->getAttributes();
            unset($row['id'], $row['schedule_start'], $row['schedule_end']);
            $row['public_id'] = (string) Str::uuid();
            $row['identifier'] = 'PLAN-'.(100 + $i);
            $rows[] = $row;
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            Issue::insert($chunk);
        }
        $data = $this->getJson($url.'&perPage=1000')->assertOk()->json('data');
        $this->assertCount(1000, $data['items']);
        $this->assertSame(1001, $data['total']);
        $this->getJson($url.'&perPage=1001')->assertStatus(400);
    }

    /** 建立已驗證本人與專案
     * @return array{array,array} 身分與專案
     */
    private function fixture(): array
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Schedule', 'email' => Str::uuid().'@example.test', 'password' => 'schedule-password', 'workspaceName' => 'Schedule'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Plan', 'code' => 'PLAN', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$identity, $project];
    }

    /** 建立任務後保留真實外鍵, 日期與指派由fixture設定
     * @param  array  $project  專案
     * @param  array  $fields  合成資料
     * @return Issue 已保存任務
     */
    private function issue(array $project, array $fields = []): Issue
    {
        $row = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => '排程 '.Str::uuid(), 'description' => 'must-not-read-description', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = Issue::where('public_id', $row['id'])->firstOrFail();
        $issue->update($fields);

        return $issue;
    }

    /** 區間首尾都算重疊, 單端視為單日, 到期日不參與, 兩端皆空只計數
     * @return void
     */
    public function test_overlap_single_ended_and_unscheduled(): void
    {
        [, $project] = $this->fixture();
        $url = '/api/v1/projects/'.$project['id'].'/schedule?from=2026-11-30&to=2026-12-06';
        $across = $this->issue($project, ['start_date' => '2026-11-20', 'end_date' => '2026-12-31']);
        $endsOnFrom = $this->issue($project, ['start_date' => '2026-11-25', 'end_date' => '2026-11-30']);
        $startsOnTo = $this->issue($project, ['start_date' => '2026-12-06', 'end_date' => '2026-12-09']);
        $onlyStart = $this->issue($project, ['start_date' => '2026-12-01']);
        $onlyEnd = $this->issue($project, ['end_date' => '2026-12-02']);
        $this->issue($project, ['start_date' => '2026-11-01', 'end_date' => '2026-11-29']);
        $this->issue($project, ['start_date' => '2026-12-07']);
        $this->issue($project, ['end_date' => '2026-11-29']);
        $this->issue($project, ['due_date' => '2026-12-01']);
        $this->issue($project);

        $data = $this->getJson($url)->assertOk()->json('data');
        $this->assertSame([$across->public_id, $endsOnFrom->public_id, $onlyStart->public_id, $onlyEnd->public_id, $startsOnTo->public_id], array_column($data['items'], 'id'));
        $this->assertSame(2, $data['unscheduled']);
        $this->assertSame('', $data['items'][2]['endDate']);
        $this->assertSame('', $data['items'][3]['startDate']);
        $this->assertArrayNotHasKey('description', $data['items'][0]);
        $this->assertSame(['id', 'identifier', 'title', 'type', 'priority', 'state', 'stateLabel', 'assignee', 'assigneeName', 'assigneeDisabled', 'startDate', 'endDate', 'dueDate', 'epicId', 'revision'], array_keys($data['items'][0]));
        $this->assertSame([], $data['epics']);
    }

    /** 換頁不重複不漏, 承辦者篩選同時套用到未排程數
     * @return void
     */
    public function test_stable_pagination_and_assignee_filter(): void
    {
        [$identity, $project] = $this->fixture();
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
        $ids = [];
        foreach (range(1, 5) as $index) {
            $ids[] = $this->issue($project, ['start_date' => '2026-02-28', 'end_date' => '2026-03-01', 'assignee_id' => $index <= 2 ? $member->id : null])->public_id;
        }
        $this->issue($project, ['assignee_id' => $member->id]);
        $url = '/api/v1/projects/'.$project['id'].'/schedule?from=2026-02-01&to=2026-03-31&perPage=2';
        $seen = [];
        foreach (range(1, 3) as $page) {
            $seen = [...$seen, ...array_column($this->getJson($url.'&page='.$page)->assertOk()->json('data.items'), 'id')];
        }
        $this->assertSame(Issue::whereIn('public_id', $ids)->orderBy('identifier')->pluck('public_id')->all(), $seen);
        $mine = $this->getJson($url.'&assignee='.$identity['user']['id'])->assertOk()->json('data');
        $this->assertSame(2, $mine['total']);
        $this->assertSame(1, $mine['unscheduled']);
        $this->assertSame($identity['user']['id'], $mine['items'][0]['assignee']);
    }

    /** TT-446：未排程清單與計數同條件, 只回列表欄位, 補日期後離開清單並出現在時程, 撤權即不可讀
     * @return void
     */
    public function test_unscheduled_list_matches_count_and_access(): void
    {
        [$identity, $project] = $this->fixture();
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
        $other = $this->postJson('/api/rpc/createProject', ['name' => 'Other', 'code' => 'OTHER', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->issue($other);
        $mine = $this->issue($project, ['assignee_id' => $member->id]);
        $loose = $this->issue($project);
        $closed = $this->issue($project);
        $closed->update(['state_id' => ProjectStatus::where('project_id', $closed->project_id)->where('code', 'closed')->value('id')]);
        $this->issue($project, ['start_date' => '2026-05-01']);
        $this->issue($project, ['due_date' => '2026-05-02']);
        $base = '/api/v1/projects/'.$project['id'];

        $data = $this->getJson($base.'/schedule/unscheduled')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$mine->public_id, $loose->public_id, Issue::where('project_id', $mine->project_id)->whereNotNull('due_date')->value('public_id')], array_column($data['items'], 'id'));
        $this->assertSame($this->getJson($base.'/schedule?from=2026-05-01&to=2026-05-07')->json('data.unscheduled'), $data['total']);
        $this->assertSame(['id', 'identifier', 'title', 'type', 'priority', 'state', 'stateLabel', 'assignee', 'assigneeName', 'assigneeDisabled', 'startDate', 'endDate', 'dueDate', 'epicId', 'revision'], array_keys($data['items'][0]));
        $this->assertCount(4, $this->getJson($base.'/schedule/unscheduled?includeClosed=1')->json('data.items'));
        $this->assertSame([$mine->public_id], array_column($this->getJson($base.'/schedule/unscheduled?assignee='.$identity['user']['id'])->json('data.items'), 'id'));
        $paged = $this->getJson($base.'/schedule/unscheduled?perPage=2&page=2')->assertOk()->json('data');
        $this->assertSame([2, 2, 3], [$paged['page'], $paged['lastPage'], $paged['total']]);
        $this->getJson($base.'/schedule/unscheduled?perPage=51')->assertStatus(400);
        $this->getJson($base.'/schedule/unscheduled?workspaceId=x&from=2026-01-01')->assertOk();

        // 沿用既有卡片更新與 revision 衝突處理, 補日期後從清單移到時程
        $this->postJson('/api/rpc/updateIssue', ['id' => $loose->public_id, 'startDate' => '2026-05-03', 'endDate' => '2026-05-04', 'expectedRevision' => $loose->fresh()->revision + 1, 'requestId' => (string) Str::uuid()])->assertJsonPath('error.code', 'REVISION_CONFLICT');
        $this->postJson('/api/rpc/updateIssue', ['id' => $loose->public_id, 'startDate' => '2026-05-03', 'endDate' => '2026-05-04', 'expectedRevision' => $loose->fresh()->revision, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->assertNotContains($loose->public_id, array_column($this->getJson($base.'/schedule/unscheduled')->json('data.items'), 'id'));
        $this->assertContains($loose->public_id, array_column($this->getJson($base.'/schedule?from=2026-05-01&to=2026-05-07')->json('data.items'), 'id'));

        $member->update(['role' => 'member']);
        $this->assertContains($this->getJson($base.'/schedule/unscheduled')->status(), [403, 404]);
        $member->update(['role' => 'owner']);
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Another', 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson($base.'/schedule/unscheduled')->assertStatus(404);
    }

    /** 跨度、格式與順序在查詢前拒絕
     * @return void
     */
    public function test_range_validation(): void
    {
        [, $project] = $this->fixture();
        $base = '/api/v1/projects/'.$project['id'].'/schedule';
        $this->getJson($base.'?from=2026-01-01&to=2026-04-02')->assertOk();
        $this->getJson($base.'?from=2026-01-01&to=2026-04-03')->assertStatus(400);
        $this->getJson($base.'?from=2026-03-02&to=2026-03-01')->assertStatus(400);
        $this->getJson($base.'?from=2026-02-30&to=2026-03-01')->assertStatus(400);
        $this->getJson($base.'?from=2026-03-01')->assertStatus(400);
        $this->getJson($base.'?from=2026-03-01&to=2026-03-02&perPage=1001')->assertStatus(400);
    }

    /** 移除專案資格後立即不可讀, 切工作區不帶出舊租戶
     * @return void
     */
    public function test_permission_revocation_and_workspace_isolation(): void
    {
        [$identity, $project] = $this->fixture();
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
        $issue = $this->issue($project, ['start_date' => '2026-05-01']);
        $url = '/api/v1/projects/'.$project['id'].'/schedule?from=2026-05-01&to=2026-05-07';
        $member->update(['role' => 'member']);
        ProjectMember::create(['public_id' => $project['id'].':'.$member->user_id, 'workspace_id' => $member->workspace_id, 'project_id' => $issue->project_id, 'member_id' => $member->id, 'role_id' => 'developer', 'revision' => 1]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 1);
        ProjectMember::where('member_id', $member->id)->update(['removed_at' => now()]);
        $this->assertContains($this->getJson($url)->status(), [403, 404]);
        $member->update(['role' => 'owner']);
        $this->getJson($url)->assertOk();
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Another', 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson($url)->assertStatus(404);
    }

    /** 甘特分組：卡片帶同Project Epic公開ID, 回應附當頁引用的Epic摘要；Epic沒有日期也有名稱（跨Project由外鍵阻擋）
     * @return void
     */
    public function test_epic_grouping_summary(): void
    {
        [, $project] = $this->fixture();
        $epic = $this->issue($project, ['type' => 'epic', 'title' => '會員Epic']);
        $child = $this->issue($project, ['start_date' => '2026-12-01', 'end_date' => '2026-12-03', 'epic_id' => $epic->id]);
        $loose = $this->issue($project, ['start_date' => '2026-12-02']);

        $data = $this->getJson('/api/v1/projects/'.$project['id'].'/schedule?from=2026-11-30&to=2026-12-06')->assertOk()->json('data');
        $byId = array_column($data['items'], 'epicId', 'id');
        $this->assertSame($epic->public_id, $byId[$child->public_id]);
        $this->assertSame('', $byId[$loose->public_id]);
        $this->assertSame([['id' => $epic->public_id, 'identifier' => $epic->identifier, 'title' => '會員Epic']], $data['epics']);
    }
}
