<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use App\Services\WorkspaceTimeReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
        $limited = (new WorkspaceTimeReport((new RecordStore)->forWorkspace($owner['workspace']['id']), $limitedSession))->get($range);
        $this->assertSame(155, $limited['summary']['totalMinutes'], '報表入口權限不跳過逐專案團隊工時資格');
        $this->assertSame([$first['id']], array_values(array_unique(array_column($limited['allocations'], 'projectId'))));
        $this->assertSame([$first['id']], array_column($limited['options']['projects'], 'id'));
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
}
