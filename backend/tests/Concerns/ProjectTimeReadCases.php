<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 專案工時月曆在memory與獨立MySQL共用的範圍斷言。 */
trait ProjectTimeReadCases
{
    /**
     * 月曆只聚合目前Project、整月與有效工時；Epic本身與所屬卡片去重；當日明細按需分頁。
     *
     * @return void
     */
    public function test_project_time_calendar_is_scoped_aggregated_and_paged(): void
    {
        $email = 'time-'.Str::uuid().'@example.test';
        $identity = $this->postJson('/api/auth/register', ['name' => 'Time Owner', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => 'Time'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Time', 'code' => 'TIME'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTHR'])->assertOk()->json('data');
        $epic = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Epic', 'type' => 'epic'])->assertOk()->json('data');
        $task = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Task', 'epicId' => $epic['id'], 'estimateMinutes' => 120, 'description' => str_repeat('private description ', 200)])->assertOk()->json('data');
        $loose = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Loose', 'estimateMinutes' => 60])->assertOk()->json('data');
        $outside = $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Outside'])->assertOk()->json('data');
        $log = fn (array $issue, string $date, int $minutes) => $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => $date, 'minutes' => $minutes, 'note' => 'n'])->assertOk()->json('data');
        $log($task, '2026-10-05', 60);
        $log($epic, '2026-10-05', 30);
        $log($loose, '2026-10-07', 45);
        $log($outside, '2026-10-05', 100);
        $log($task, '2026-09-30', 50);
        $voided = $log($loose, '2026-10-07', 15);
        $this->rpc('voidTime', ['id' => $voided['id'], 'expectedRevision' => $voided['revision']])->assertOk();
        for ($index = 0; $index < 22; $index++) {
            $log($loose, '2026-10-09', 5);
        }

        $developer = User::create(['id' => (string) Str::uuid(), 'name' => 'Dev', 'email' => 'dev-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        $reader = User::create(['id' => (string) Str::uuid(), 'name' => 'Reader', 'email' => 'reader-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        foreach ([$developer, $reader] as $user) {
            $store->put('members', $user->id, ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'user']);
        }
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $developer->id, 'roleId' => 'developer', 'expectedRevision' => 0])->assertOk();
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'roleId' => 'reader', 'expectedRevision' => 1])->assertOk();
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $developer->email, 'password' => 'test-local-password'])->assertOk();
        $this->rpc('logTime', ['issueId' => $task['id'], 'workDate' => '2026-10-05', 'minutes' => 20, 'note' => 'dev'])->assertOk();

        $path = '/api/v1/projects/'.$project['id'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $summary = $this->getJson($path.'/time-calendar?month=2026-10')->assertOk()->json('data');
        $this->assertSame(['month' => '2026-10', 'member' => '', 'team' => true], array_intersect_key($summary, ['month' => 1, 'member' => 1, 'team' => 1]));
        $this->assertSame(60 + 30 + 45 + 110 + 20, $summary['total'], '不含其他Project、上月與作廢紀錄');
        $this->assertSame([['date' => '2026-10-05', 'minutes' => 110, 'count' => 3], ['date' => '2026-10-07', 'minutes' => 45, 'count' => 1], ['date' => '2026-10-09', 'minutes' => 110, 'count' => 22]], $summary['days']);
        $this->assertSame([[$identity['user']['id'], 245], [$developer->id, 20]], array_map(fn ($row) => [$row['id'], $row['minutes']], $summary['members']));
        $this->assertSame([[$loose['id'], 155, 60], [$task['id'], 80, 120], [$epic['id'], 30, 0]], array_map(fn ($row) => [$row['id'], $row['spent'], $row['estimateMinutes']], $summary['issues']));
        $this->assertSame([['id' => $epic['id'], 'identifier' => $epic['identifier'], 'title' => 'Epic', 'type' => 'epic', 'spentMinutes' => 110, 'estimateMinutes' => 120]], $summary['epics'], 'Epic本身30＋所屬卡片80，各計一次');
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'description') || str_contains($q['query'], 'audit_events') || str_contains($q['query'], 'comments')));

        $mine = $this->getJson($path.'/time-calendar?month=2026-10&member='.$developer->id)->assertOk()->json('data');
        $this->assertSame([20, [[$developer->id, 20]]], [$mine['total'], array_map(fn ($row) => [$row['id'], $row['minutes']], $mine['members'])]);
        $this->assertSame(0, $this->getJson($path.'/time-calendar?month=2026-11')->assertOk()->json('data.total'));

        $first = $this->getJson($path.'/time-entries?date=2026-10-09')->assertOk()->assertJsonPath('data.total', 22)->assertJsonCount(20, 'data.items')->json('data.items');
        $second = $this->getJson($path.'/time-entries?date=2026-10-09&page=2')->assertOk()->assertJsonCount(2, 'data.items')->json('data.items');
        $this->assertSame([], array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->assertSame(['2026-10-09', $loose['id'], $project['id']], [$first[0]['workDate'], $first[0]['issueId'], $first[0]['projectId']]);
        $day = $this->getJson($path.'/time-entries?date=2026-10-05')->assertOk()->json('data');
        $this->assertSame(3, $day['total'], '當日只含目前Project有效紀錄');

        foreach (['month=2026-13', 'month=26-10', 'month=2026-1x'] as $query) {
            $this->getJson($path.'/time-calendar?'.$query)->assertStatus(400);
        }
        $this->getJson($path.'/time-calendar')->assertStatus(400);
        $this->getJson($path.'/time-entries?date=2026-02-30')->assertStatus(400);
        $this->getJson($path.'/time-entries?date=2026-10-05&perPage=101')->assertStatus(400);
        $this->getJson($path.'/time-calendar?month=2026-10', ['X-Workspace-ID' => 'wrong'])->assertStatus(409);
        $this->getJson('/api/v1/projects/'.$other['id'].'/time-calendar?month=2026-10')->assertStatus(404);

        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $reader->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson($path.'/time-calendar?month=2026-10')->assertStatus(403);
        $this->getJson($path.'/time-entries?date=2026-10-05')->assertStatus(403);

        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'test-local-password'])->assertOk();
        $this->rpc('removeProjectMember', ['projectId' => $project['id'], 'userId' => $developer->id, 'expectedRevision' => 2])->assertOk();
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $developer->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson($path.'/time-calendar?month=2026-10')->assertStatus(404);
        $this->getJson($path.'/time-entries?date=2026-10-05')->assertStatus(404);
    }
}
