<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\ProjectMember;
use App\Models\ProjectStatus;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** 用隔離MySQL驗證首頁本人範圍、日期邊界、撤權與有界投影。 */
class PersonalDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** 建立已驗證本人與專案
     * @return array{array,array} 身分與專案
     */
    private function fixture(): array
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Dashboard', 'email' => Str::uuid().'@example.test', 'password' => 'dashboard-password', 'workspaceName' => 'Dashboard'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'My project', 'code' => 'DASH', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$identity, $project];
    }

    /** 建立任務後保留真實外鍵, 指派與日期由fixture設定
     * @param  array  $project  專案
     * @param  array  $fields  合成資料
     * @return Issue 已保存任務
     */
    private function issue(array $project, array $fields = []): Issue
    {
        $row = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => '工作 '.Str::uuid(), 'description' => 'must-not-read-description', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = Issue::where('public_id', $row['id'])->firstOrFail();
        $issue->update($fields);

        return $issue;
    }

    /** 自訂條件在SQL交集篩選, 不跨工作區或忽略成員資格。 */
    public function test_custom_task_filters_are_bounded_and_scoped(): void
    {
        [$identity, $project] = $this->fixture();
        $matching = $this->issue($project, ['priority' => 'high', 'due_date' => '2026-12-01']);
        $this->issue($project, ['priority' => 'low', 'due_date' => '2026-12-01']);
        $query = '/api/v1/personal-dashboard?kind=tasks&assignee=all&priority=high&dueFrom=2026-12-01&dueTo=2026-12-01&projectId='.$project['id'];
        $this->assertSame([$matching->public_id], array_column($this->getJson($query)->assertOk()->json('data.items'), 'id'));
        $this->getJson($query.'&state=closed')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/personal-dashboard?kind=tasks&dueFrom=bad')->assertStatus(400);
        $this->getJson('/api/v1/personal-dashboard?kind=tasks&projectId='.Str::uuid())->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Another', 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson($query)->assertOk()->assertJsonPath('data.total', 0);
    }

    /** 只讀本人, 日期不混用到期與開始, SQL分頁不輸出描述
     * @return void
     */
    public function test_personal_dates_pagination_and_minimal_projection(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-24 00:30:00', 'Asia/Taipei'));
        try {
            [$identity, $project] = $this->fixture();
            $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
            $this->issue($project); // 非本人不出現
            $overdue = $this->issue($project, ['assignee_id' => $member->id, 'due_date' => '2026-11-23']);
            $start = $this->issue($project, ['assignee_id' => $member->id, 'start_date' => '2026-12-01']);
            $this->issue($project, ['assignee_id' => $member->id, 'start_date' => '2026-12-02', 'due_date' => '2026-12-02']);
            $mine = $this->getJson('/api/v1/personal-dashboard?kind=mine&perPage=1')->assertOk()->json('data');
            $this->assertSame(3, $mine['total']);
            $this->assertCount(1, $mine['items']);
            $this->assertArrayNotHasKey('description', $mine['items'][0]);
            $this->assertArrayNotHasKey('history', $mine['items'][0]);
            $this->assertSame([$overdue->public_id], array_column($this->getJson('/api/v1/personal-dashboard?kind=due')->assertOk()->json('data.items'), 'id'));
            $this->assertSame([$start->public_id], array_column($this->getJson('/api/v1/personal-dashboard?kind=starting')->assertOk()->json('data.items'), 'id'));
            $this->getJson('/api/v1/personal-dashboard?kind=all')->assertStatus(400);
            $this->getJson('/api/v1/personal-dashboard?kind=mine&perPage=21')->assertStatus(400);
            $this->getJson('/api/v1/personal-dashboard?kind=mine&page=502')->assertStatus(400);
        } finally {
            Carbon::setTestNow();
        }
    }

    /** 指派不代表可讀, 移除專案資格後立即不再顯示
     * @return void
     */
    public function test_project_permission_revocation_and_closed_state(): void
    {
        [$identity, $project] = $this->fixture();
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
        $issue = $this->issue($project, ['assignee_id' => $member->id]);
        // 依有效permission映射驗一般成員, 不讓owner的readAny掩蓋撤權。
        $member->update(['role' => 'member']);
        ProjectMember::create(['public_id' => $project['id'].':'.$member->user_id, 'workspace_id' => $member->workspace_id, 'project_id' => $issue->project_id, 'member_id' => $member->id, 'role_id' => 'developer', 'revision' => 1]);
        $this->getJson('/api/v1/personal-dashboard?kind=mine')->assertOk()->assertJsonPath('data.total', 1);
        $issue->update(['state_id' => ProjectStatus::where('project_id', $issue->project_id)->where('code', 'closed')->value('id')]);
        $this->getJson('/api/v1/personal-dashboard?kind=mine')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/personal-dashboard?kind=recent')->assertOk()->assertJsonPath('data.total', 1);
        ProjectMember::where('member_id', $member->id)->update(['removed_at' => now()]);
        $this->getJson('/api/v1/personal-dashboard?kind=mine')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/personal-dashboard?kind=recent')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/personal-dashboard?kind=tasks&assignee=all')->assertOk()->assertJsonPath('data.total', 0);
    }

    /** 切工作區不能帶出舊租戶任務, 舊標頭亦不能跨分頁誤讀
     * @return void
     */
    public function test_workspace_switch_isolates_personal_tasks(): void
    {
        [$identity, $project] = $this->fixture();
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
        $this->issue($project, ['assignee_id' => $member->id]);
        $this->getJson('/api/v1/personal-dashboard?kind=mine')->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson('/api/auth/createWorkspace', ['workspaceName' => 'Another', 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson('/api/v1/personal-dashboard?kind=mine')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/personal-dashboard?kind=mine', ['X-Workspace-ID' => $identity['workspace']['id']])->assertStatus(409);
    }
}
