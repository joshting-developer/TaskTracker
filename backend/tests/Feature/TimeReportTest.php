<?php

namespace Tests\Feature;

use App\Data\TimeReportData;
use App\Domain\DomainError;
use App\Models\Issue;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Repositories\RecordStore;
use App\Repositories\TimeReportRepository;
use App\Services\WorkspaceTimeReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** 驗證工時報表的加總、租戶與逐專案授權，以及日期邊界。 */
class TimeReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 呼叫真實API並為寫入提供獨立重試識別碼。
     *
     * @param  string  $operation  API操作
     * @param  array  $input  測試輸入
     * @return TestResponse JSON回應
     */
    private function rpc(string $operation, array $input = []): TestResponse
    {
        return $this->postJson('/api/rpc/'.$operation, [
            'requestId' => (string) Str::uuid(),
            ...$input,
        ]);
    }

    /**
     * 建立獨立擁有者或受邀成員。
     *
     * @param  string  $email  測試信箱
     * @param  string|null  $token  邀請token
     * @return array 帳號及空間資格
     */
    private function register(string $email, ?string $token = null): array
    {
        Auth::logout();

        return $this->postJson('/api/auth/register', [
            'name' => $email,
            'email' => $email,
            'password' => 'report-test-password',
            'workspaceName' => '分析測試',
            'inviteToken' => $token,
        ])->assertOk()->json('data');
    }

    /**
     * 切換成測試帳號與其伺服器工作空間。
     *
     * @param  array  $identity  後端註冊回傳資格
     * @return void
     */
    private function signIn(array $identity): void
    {
        $this->actingAs(User::findOrFail($identity['user']['id']))->withSession(['workspace_id' => $identity['workspace']['id']]);
    }

    /**
     * 原始工時各算一次，涵蓋作廢／日期／零日／成員／專案及外租戶。
     *
     * @return void
     */
    public function test_totals_filters_dates_and_workspace_isolation(): void
    {
        $this->rpc('getTimeReport')->assertStatus(401);
        $owner = $this->register('report-owner@example.test');
        $first = $this->rpc('createProject', [
            'name' => '第一專案',
            'code' => 'FIRST',
        ])->assertOk()->json('data');
        $second = $this->rpc('createProject', [
            'name' => '第二專案',
            'code' => 'SECOND',
        ])->assertOk()->json('data');
        $epic = $this->rpc('createIssue', [
            'projectId' => $first['id'],
            'title' => '大目標',
            'type' => 'epic',
        ])->assertOk()->json('data');
        $child = $this->rpc('createIssue', [
            'projectId' => $first['id'],
            'title' => '所屬工作',
            'epicId' => $epic['id'],
        ])->assertOk()->json('data');
        $task = $this->rpc('createIssue', [
            'projectId' => $first['id'],
            'title' => '獨立工作',
        ])->assertOk()->json('data');
        $bug = $this->rpc('createIssue', [
            'projectId' => $second['id'],
            'title' => '修正問題',
            'type' => 'bug',
        ])->assertOk()->json('data');
        $today = CarbonImmutable::today('Asia/Taipei');
        $start = $today->subDays(2)->format('Y-m-d');
        $range = [
            'startDate' => $start,
            'endDate' => $today->format('Y-m-d'),
        ];
        foreach ([
            [$epic, 30],
            [$child, 45],
            [$task, 60],
            [$bug, 120],
        ] as [$issue, $minutes]) {
            $this->rpc('logTime', [
                'issueId' => $issue['id'],
                'workDate' => $start,
                'minutes' => $minutes,
                'note' => '工作說明 '.$minutes,
            ])->assertOk();
        }
        $voided = $this->rpc('logTime', [
            'issueId' => $bug['id'],
            'workDate' => $start,
            'minutes' => 10,
        ])->assertOk()->json('data');
        $this->rpc('voidTime', [
            'id' => $voided['id'],
            'expectedRevision' => $voided['revision'],
        ])->assertOk();
        $this->rpc('logTime', [
            'issueId' => $bug['id'],
            'workDate' => $today->subDays(3)->format('Y-m-d'),
            'minutes' => 100,
        ])->assertOk();
        $invite = $this->rpc('inviteMember', ['email' => 'report-member@example.test'])->assertOk()->json('data');
        $member = $this->register($invite['email'], $invite['token']);
        $this->signIn($owner);
        $acl = $this->rpc('getProjectMembers', ['projectId' => $first['id']])->assertOk()->json('data');
        $this->rpc('setProjectMember', [
            'projectId' => $first['id'],
            'userId' => $member['user']['id'],
            'roleId' => 'developer',
            'expectedRevision' => $acl['revision'],
        ])->assertOk();
        $this->signIn($member);
        $this->rpc('logTime', [
            'issueId' => $task['id'],
            'workDate' => $today->format('Y-m-d'),
            'minutes' => 20,
        ])->assertOk();
        $this->rpc('getTimeReport', [
            ...$range,
            'permissions' => ['workspace.timeReports.read'],
            'role' => 'owner',
        ])->assertStatus(403);
        $other = $this->register('report-foreign@example.test');
        $foreign = $this->rpc('createProject', [
            'name' => '不可洩漏',
            'code' => 'FOREIGN',
        ])->assertOk()->json('data');
        $foreignIssue = $this->rpc('createIssue', [
            'projectId' => $foreign['id'],
            'title' => '其他租戶',
        ])->assertOk()->json('data');
        $this->rpc('logTime', [
            'issueId' => $foreignIssue['id'],
            'workDate' => $start,
            'minutes' => 500,
        ])->assertOk();
        $this->signIn($owner);
        $report = $this->rpc('getTimeReport', [
            ...$range,
            'workspaceId' => $other['workspace']['id'],
        ])->assertOk()->json('data');
        $this->assertSame(275, $report['summary']['totalMinutes']);
        $this->assertSame(5, $report['summary']['entryCount']);
        $this->assertSame(2, $report['summary']['activeDays']);
        $this->assertSame([255, 0, 20], array_column($report['days'], 'minutes'));
        $this->assertSame([255, 20], array_column($report['members'], 'minutes'));
        $this->assertSame([155, 120], array_column($report['projects'], 'minutes'));
        $this->assertSame(275, array_sum(array_column($report['allocations'], 'minutes')));
        $this->assertCount(3, $report['allocations']);
        foreach ($report['allocations'] as $allocation) {
            $this->assertNotSame($foreign['id'], $allocation['projectId']);
            $this->assertArrayNotHasKey('note', $allocation);
        }
        foreach ($report['members'] as $row) {
            $this->assertSame($row['minutes'], array_sum(array_column(array_filter($report['allocations'], fn ($item) => $item['userId'] === $row['id']), 'minutes')));
        }
        $this->assertSame($report['summary']['totalMinutes'], array_sum(array_column($report['members'], 'minutes')));
        $this->assertNotContains($foreign['id'], array_column($report['options']['projects'], 'id'));
        $this->assertNotContains($other['user']['id'], array_column($report['options']['members'], 'id'));
        $this->rpc('getTimeReport', [
            ...$range,
            'projectId' => $foreign['id'],
        ])->assertStatus(404);
        $this->rpc('getTimeReport', [
            ...$range,
            'userId' => $other['user']['id'],
        ])->assertStatus(404);
        $filtered = $this->rpc('getTimeReport', [
            ...$range,
            'projectId' => $first['id'],
            'userId' => $member['user']['id'],
        ])->assertOk()->json('data');
        $this->assertSame(20, $filtered['summary']['totalMinutes']);
        $limitedSession = [
            'user' => $member['user'],
            'workspace' => $owner['workspace'],
            'permissions' => ['workspace.read', 'workspace.timeReports.read'],
        ];
        $limited = (new WorkspaceTimeReport((new RecordStore)->forWorkspace($owner['workspace']['id']), $limitedSession, app(TimeReportRepository::class)))->get($range);
        $this->assertSame(155, $limited['summary']['totalMinutes'], '報表入口權限不跳過逐專案團隊工時資格');
        $this->assertSame([$first['id']], array_values(array_unique(array_column($limited['allocations'], 'projectId'))));
        $this->assertSame([$first['id']], array_column($limited['options']['projects'], 'id'));
        $details = $this->rpc('getTimeReport', [
            ...$range, 'userId' => $owner['user']['id'], 'detailGroup' => 'dates',
        ])->assertOk()->json('data.details');
        $this->assertCount(4, $details['rows']);
        $this->assertSame(255, array_sum(array_column($details['rows'], 'minutes')));
        $this->assertNull($details['expanded']);
        $taskKey = $start.'|'.$task['id'];
        $expanded = $this->rpc('getTimeReport', [
            ...$range, 'userId' => $owner['user']['id'], 'detailGroup' => 'dates', 'entryGroupKey' => $taskKey,
        ])->assertOk()->json('data.details.expanded');
        $this->assertCount(1, $expanded['rows']);
        $this->assertSame('工作說明 60', $expanded['rows'][0]['note']);
        $this->assertArrayNotHasKey('history', $expanded['rows'][0]);
        $this->assertArrayNotHasKey('userId', $expanded['rows'][0]);
        $limitedDetails = (new WorkspaceTimeReport((new RecordStore)->forWorkspace($owner['workspace']['id']), $limitedSession, app(TimeReportRepository::class)))->get([
            ...$range, 'userId' => $owner['user']['id'], 'detailGroup' => 'issues',
        ]);
        $this->assertSame([$first['id']], array_values(array_unique(array_column($limitedDetails['details']['rows'], 'projectId'))));
        $aggregateOnlySession = [...$limitedSession, 'permissions' => [
            ...$limitedSession['permissions'], 'project.readAny', 'timeEntry.readTeamAny',
        ]];
        $aggregateOnly = new WorkspaceTimeReport((new RecordStore)->forWorkspace($owner['workspace']['id']), $aggregateOnlySession, app(TimeReportRepository::class));
        $this->assertSame(275, $aggregateOnly->get($range)['summary']['totalMinutes']);
        $visibleDetails = $aggregateOnly->get([...$range, 'userId' => $owner['user']['id'], 'detailGroup' => 'issues']);
        $this->assertSame(135, $visibleDetails['summary']['totalMinutes'], '明細卡片名稱仍需issue.read，不能只靠團隊工時權限');
        $this->assertSame([$first['id']], array_values(array_unique(array_column($visibleDetails['details']['rows'], 'projectId'))));
        foreach ([$bug['id'], $foreignIssue['id']] as $hiddenKey) {
            try {
                (new WorkspaceTimeReport((new RecordStore)->forWorkspace($owner['workspace']['id']), $limitedSession, app(TimeReportRepository::class)))->get([
                    ...$range, 'userId' => $owner['user']['id'], 'detailGroup' => 'issues', 'entryGroupKey' => $hiddenKey,
                ]);
                $this->fail('不可展開未授權的卡片工時');
            } catch (DomainError $error) {
                $this->assertSame('NOT_FOUND', $error->errorCode);
            }
        }

        $this->rpc('getTimeReport', [
            'startDate' => '2024-01-01',
            'endDate' => '2024-12-31',
        ])->assertOk()->assertJsonCount(366, 'data.days');
        $this->rpc('getTimeReport', [
            'startDate' => '2024-01-01',
            'endDate' => '2025-01-01',
        ])->assertStatus(400);
        $empty = $this->rpc('getTimeReport', [
            ...$range,
            'projectId' => $second['id'],
            'userId' => $member['user']['id'],
        ])->assertOk()->json('data');
        $this->assertSame(0, $empty['summary']['totalMinutes']);
        $this->assertSame([], $empty['members']);
        foreach ([
            ['startDate' => "2026-\0-01"],
            ['startDate' => '2026-02-30'],
            ['endDate' => '2000-01-01'],
            ['startDate' => '2020-01-01'],
            ['userId' => []],
        ] as $invalid) {
            $this->rpc('getTimeReport', [...$range, ...$invalid])->assertStatus(400);
        }
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => $member['user']['id'],
            'roleId' => 'manager',
            'expectedRevision' => 0,
        ])->assertOk();
        $this->signIn($member);
        $this->rpc('getTimeReport', $range)->assertOk()->assertJsonPath('data.summary.totalMinutes', 275);
        $this->signIn($owner);
        $this->rpc('setWorkspaceMemberRole', [
            'userId' => $member['user']['id'],
            'roleId' => 'user',
            'expectedRevision' => 1,
        ])->assertOk();
        $this->signIn($member);
        $this->rpc('getTimeReport', $range)->assertStatus(403);
    }

    /** 跨日期與同卡大量紀錄均分頁，總計不因頁碼改變。 */
    public function test_member_details_pagination_and_input_validation(): void
    {
        $owner = $this->register('report-detail-owner@example.test');
        $project = $this->rpc('createProject', ['name' => '工時明細', 'code' => 'DETAIL'])->assertOk()->json('data');
        $task = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '多日投入'])->assertOk()->json('data');
        $bug = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '同日多筆', 'type' => 'bug'])->assertOk()->json('data');
        $today = CarbonImmutable::today('Asia/Taipei');
        for ($day = 0; $day < 23; $day++) {
            $this->rpc('logTime', ['issueId' => $task['id'], 'workDate' => $today->subDays($day)->format('Y-m-d'), 'minutes' => 1, 'note' => '第'.$day.'日'])->assertOk();
        }
        for ($index = 0; $index < 21; $index++) {
            $this->rpc('logTime', ['issueId' => $bug['id'], 'workDate' => $today->format('Y-m-d'), 'minutes' => 1, 'note' => '<script>測試</script> '.$index])->assertOk();
        }
        $voided = $this->rpc('logTime', ['issueId' => $task['id'], 'workDate' => $today->format('Y-m-d'), 'minutes' => 100, 'note' => '不可回傳的作廢說明'])->assertOk()->json('data');
        $this->rpc('voidTime', ['id' => $voided['id'], 'expectedRevision' => $voided['revision']])->assertOk();
        $query = ['startDate' => $today->subDays(27)->format('Y-m-d'), 'endDate' => $today->format('Y-m-d'), 'userId' => $owner['user']['id'], 'detailGroup' => 'dates'];
        $first = $this->rpc('getTimeReport', $query)->assertOk()->json('data');
        $this->assertSame(44, $first['summary']['totalMinutes']);
        $this->assertSame(24, $first['details']['total']);
        $this->assertCount(20, $first['details']['rows']);
        $this->assertSame(22, $first['details']['rows'][0]['dayMinutes']);
        $second = $this->rpc('getTimeReport', [...$query, 'detailPage' => 2])->assertOk()->json('data');
        $this->assertCount(4, $second['details']['rows']);
        $this->assertSame($first['summary'], $second['summary']);
        $this->assertSame([], array_intersect(array_column($first['details']['rows'], 'key'), array_column($second['details']['rows'], 'key')));
        $this->rpc('getTimeReport', [...$query, 'detailPage' => 999])->assertOk()->assertJsonPath('data.details.page', 2);
        $cards = $this->rpc('getTimeReport', [...$query, 'detailGroup' => 'issues'])->assertOk()->json('data.details');
        $this->assertCount(2, $cards['rows']);
        $this->assertSame([23, 21], array_column($cards['rows'], 'minutes'));
        $this->assertSame($task['id'], $cards['rows'][0]['issueId']);
        $entries = $this->rpc('getTimeReport', [...$query, 'detailGroup' => 'issues', 'entryGroupKey' => $bug['id']])->assertOk()->json('data.details.expanded');
        $this->assertSame(21, $entries['total']);
        $this->assertCount(20, $entries['rows']);
        $lastEntries = $this->rpc('getTimeReport', [...$query, 'detailGroup' => 'issues', 'entryGroupKey' => $bug['id'], 'entryPage' => 2])->assertOk()->json('data.details.expanded');
        $this->assertCount(1, $lastEntries['rows']);
        $this->assertSame([], array_intersect(array_column($entries['rows'], 'id'), array_column($lastEntries['rows'], 'id')));
        foreach ([['detailGroup' => 'invalid'], ['userId' => ''], ['detailPage' => 0], ['detailPage' => '2'], ['entryPage' => -1], ['entryGroupKey' => []]] as $invalid) {
            $this->rpc('getTimeReport', [...$query, ...$invalid])->assertStatus(400);
        }
        $this->rpc('getTimeReport', [...$query, 'entryGroupKey' => 'missing'])->assertStatus(404);
        $empty = $this->rpc('getTimeReport', [...$query, 'startDate' => '2024-01-01', 'endDate' => '2024-01-02'])->assertOk()->json('data.details');
        $this->assertSame([], $empty['rows']);
        $this->assertSame(1, $empty['lastPage']);
    }

    /** 相同公開ID跨租戶時仍只投影本工作區，不載入歷史與不需要的說明。 */
    public function test_repository_scopes_colliding_public_ids_and_returns_only_requested_projection(): void
    {
        $today = CarbonImmutable::today('Asia/Taipei')->format('Y-m-d');
        $fixtures = [];
        foreach (['first' => 45, 'foreign' => 900] as $name => $minutes) {
            $identity = $this->register('repository-'.$name.'@example.test');
            $project = $this->rpc('createProject', ['name' => $name, 'code' => strtoupper($name)])->assertOk()->json('data');
            $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => $name])->assertOk()->json('data');
            $entry = $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => $today, 'minutes' => $minutes, 'note' => $name.'私有說明'])->assertOk()->json('data');
            $fixtures[$name] = compact('identity', 'project', 'issue', 'entry');
        }
        $first = $fixtures['first'];
        $foreign = $fixtures['foreign'];
        Project::where('public_id', $foreign['project']['id'])->update(['public_id' => $first['project']['id']]);
        Issue::where('public_id', $foreign['issue']['id'])->update(['public_id' => $first['issue']['id']]);
        TimeEntry::where('public_id', $foreign['entry']['id'])->update(['public_id' => $first['entry']['id']]);
        $repository = app(TimeReportRepository::class);
        $filters = TimeReportData::from(['startDate' => $today, 'endDate' => $today]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $summary = $repository->summary($first['identity']['workspace']['id'], [$first['project']['id']], $filters);
            $rows = $summary['allocations'];
            $this->assertCount(1, $rows);
            $this->assertSame(45, $rows[0]['minutes']);
            $this->assertSame($first['identity']['user']['id'], $rows[0]['userId']);
            $this->assertArrayNotHasKey('note', $rows[0]);
            $this->assertArrayNotHasKey('title', $rows[0]);
            $this->assertCount(2, DB::getQueryLog(), 'SQL依配對與日期聚合，不逐列查詢關聯或歷史');
        } finally {
            DB::disableQueryLog();
        }
        $details = TimeReportData::from([
            'startDate' => $today, 'endDate' => $today, 'userId' => $first['identity']['user']['id'],
            'detailGroup' => 'issues', 'entryGroupKey' => $first['issue']['id'],
        ]);
        $projects = [$first['project']['id'] => $first['project']];
        $expanded = $repository->details($first['identity']['workspace']['id'], [$first['project']['id']], $details, $projects, []);
        $this->assertSame('first私有說明', $expanded['expanded']['rows'][0]['note']);
        $this->assertSame('first', $expanded['rows'][0]['title']);
        $this->assertSame([], $repository->summary($first['identity']['workspace']['id'], [], $details)['allocations']);
    }
}
