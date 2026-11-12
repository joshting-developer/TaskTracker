<?php

namespace Tests\Feature;

use App\Data\PageQueryData;
use App\Models\Issue;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Repositories\IssueReadRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\IssueLinkQueryCases;
use Tests\Concerns\ProjectAclReadCases;
use Tests\Concerns\WorkspaceSettingsReadCases;
use Tests\MySqlTestCase;

/** 真實InnoDB驗證資料型別、DTO、關聯、receipt及作者／租戶邊界。 */
class MySqlApiTest extends MySqlTestCase
{
    use DatabaseTransactions, \Tests\Concerns\ProjectTimeReadCases, \Tests\Concerns\ProjectUtilityReadCases;
    use IssueLinkQueryCases;
    use ProjectAclReadCases;
    use WorkspaceSettingsReadCases;

    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    public function test_mysql_api_preserves_relations_time_precision_revision_and_replay(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'MySQL測試', 'email' => 'mysql-'.Str::uuid().'@example.test', 'password' => 'mysql-test-password', 'workspaceName' => '測試空間'])->assertOk();
        $project = $this->rpc('createProject', ['name' => 'MySQL專案', 'code' => 'MYSQL'])->assertOk()->json('data');
        $epic = $this->rpc('createIssue', ['projectId' => $project['id'], 'type' => 'epic', 'title' => 'Epic'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'epicId' => $epic['id'], 'title' => 'Task 中文 😀'])->assertOk()->json('data');
        $input = ['issueId' => $issue['id'], 'workDate' => now('Asia/Taipei')->toDateString(), 'minutes' => 1400, 'requestId' => (string) Str::uuid()];
        $entry = $this->rpc('logTime', $input)->assertOk()->json('data');
        $this->assertDtoSame($entry, $this->rpc('logTime', $input)->assertOk()->json('data'));
        $this->rpc('logTime', [...$input, 'requestId' => (string) Str::uuid(), 'minutes' => 41])->assertStatus(400);
        $data = $this->rpc('getWorkspace')->assertOk()->json('data');
        $saved = collect($data['issues'])->firstWhere('id', $issue['id']);
        $this->assertDtoSame($issue, $saved);
        $this->assertDtoSame($entry, collect($data['timeEntries'])->firstWhere('id', $entry['id']));
        $this->assertMatchesRegularExpression('/\.\d{6}Z$/', $saved['createdAt']);
        $this->rpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => 0, 'title' => '過期'])->assertStatus(409);
        $this->rpc('getTimeReport', ['startDate' => $input['workDate'], 'endDate' => $input['workDate']])->assertOk()->assertJsonPath('data.summary.totalMinutes', 1400);
        $this->assertSame(1, DB::table('time_entries')->where('public_id', $entry['id'])->count());
    }

    /** 原生JSON鍵排序不能導致歷史重寫，新增留言應保留舊列主鍵。 */
    public function test_native_json_history_and_comment_rows_are_not_rewritten(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Native', 'email' => 'native-'.Str::uuid().'@example.test', 'password' => 'mysql-test-password', 'workspaceName' => 'Native'])->assertOk();
        $project = $this->rpc('createProject', ['name' => 'Native', 'code' => 'NATIVE'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Native JSON'])->assertOk()->json('data');
        $first = $this->rpc('addComment', ['id' => $issue['id'], 'content' => 'First', 'expectedRevision' => $issue['revision']])->assertOk()->json('data');
        $commentId = DB::table('issue_comments')->where('public_id', $first['comments'][0]['id'])->value('id');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->rpc('addComment', ['id' => $issue['id'], 'content' => 'Second', 'expectedRevision' => $first['revision']])->assertOk();
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'audit_events') && ! str_starts_with($q['query'], 'select')));
        $this->assertSame($commentId, DB::table('issue_comments')->where('public_id', $first['comments'][0]['id'])->value('id'));
    }

    /** 原生MySQL確認literal LIKE與最小欄位投影，不以SQLite結果推論。 */
    public function test_scoped_read_filters_and_projection_on_mysql(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Read', 'email' => 'read-'.Str::uuid().'@example.test', 'password' => 'mysql-test-password', 'workspaceName' => 'Read'])->assertOk();
        $project = $this->rpc('createProject', ['name' => 'Read', 'code' => 'READ'])->assertOk()->json('data');
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '資料_100%', 'description' => 'Private detail'])->assertOk();
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '資料A100B'])->assertOk();
        $path = '/api/v1/projects/'.$project['id'].'/issues';
        $row = $this->getJson($path.'?search=%25&sort=priority')->assertOk()->assertJsonPath('data.total', 1)->json('data.items.0');
        $this->assertArrayNotHasKey('description', $row);
        $this->assertArrayNotHasKey('history', $row);
        $this->assertStringContainsString('T', $row['createdAt']);
        $this->getJson($path.'?search=%5F&sort=due')->assertOk()->assertJsonPath('data.total', 1);
    }

    /**
     * 真實MySQL驗證看板Epic展開COUNT與工時SUM相關子查詢。
     *
     * @return void
     */
    public function test_native_mysql_board_counts_and_time_projection(): void
    {
        $actor = $this->postJson('/api/auth/register', ['name' => 'Board SQL', 'email' => 'board-'.Str::uuid().'@example.test', 'password' => 'mysql-test-password', 'workspaceName' => 'Board'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Board', 'code' => 'BOARD'])->assertOk()->json('data');
        $epic = $this->rpc('createIssue', ['projectId' => $project['id'], 'type' => 'epic', 'title' => 'Epic'])->assertOk()->json('data');
        $child = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Child', 'epicId' => $epic['id']])->assertOk()->json('data');
        $this->rpc('logTime', ['issueId' => $epic['id'], 'workDate' => now('Asia/Taipei')->toDateString(), 'minutes' => 20])->assertOk();
        $this->rpc('logTime', ['issueId' => $child['id'], 'workDate' => now('Asia/Taipei')->toDateString(), 'minutes' => 30])->assertOk();
        $board = $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => 'Delivery', 'selectedIssueIds' => [$epic['id'], $child['id']]])->assertOk()->json('data');
        $base = '/api/v1/projects/'.$project['id'];
        $this->getJson($base.'/boards')->assertOk()->assertJsonPath('data.items.0.issueCount', 2);
        $this->getJson($base.'/boards/'.$board['id'].'/issues?type=epic')->assertOk()->assertJsonPath('data.items.0.spentMinutes', 50);
        $this->getJson($base.'/issues/'.$epic['id'])->assertOk()->assertJsonPath('data.spentMinutes', 50);
        $this->getJson($base.'/issues/'.$epic['id'].'/time')->assertOk()->assertJsonPath('data.total', 2)->assertJsonCount(2, 'data.items');
        $source = TimeEntry::where('issue_id', Issue::where('public_id', $child['id'])->value('id'))->firstOrFail();
        $copies = [];
        for ($index = 0; $index < 25; $index++) {
            $copy = $source->replicate();
            $copy->public_id = (string) Str::uuid();
            $copy->minutes = 1;
            $copy->work_date = now('Asia/Taipei')->subDays($index % 5)->toDateString();
            $copy->save();
            $copies[] = $copy;
        }
        $timePath = $base.'/issues/'.$epic['id'].'/time';
        $first = $this->getJson($timePath)->assertOk()->assertJsonPath('data.total', 27)->assertJsonCount(20, 'data.items')->json('data.items');
        $second = $this->getJson($timePath.'?page=2')->assertOk()->assertJsonCount(7, 'data.items')->json('data.items');
        $this->assertSame([], array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->rpc('voidTime', ['id' => $copies[0]->public_id, 'expectedRevision' => $copies[0]->revision])->assertOk();
        $this->getJson($timePath)->assertOk()->assertJsonPath('data.total', 26);
        $this->getJson($base.'/issues/'.$epic['id'])->assertOk()->assertJsonPath('data.spentMinutes', 74);
        $user = User::create(['id' => (string) Str::uuid(), 'name' => 'Other time author', 'email' => 'other-'.Str::uuid().'@example.test', 'password' => 'test-only-password']);
        $member = WorkspaceMember::where('id', $source->member_id)->firstOrFail()->replicate();
        $member->user_id = $user->id;
        $member->role = 'user';
        $member->save();
        $other = $source->replicate();
        $other->public_id = (string) Str::uuid();
        $other->member_id = $member->id;
        $other->minutes = 5;
        $other->save();
        $parent = Issue::where('public_id', $epic['id'])->firstOrFail();
        $own = app(IssueReadRepository::class)->time($parent, $actor['user']['id'], false, new PageQueryData(1, 100));
        $this->assertSame(26, $own->total());
        $this->assertSame([$actor['user']['id']], array_values(array_unique(array_column($own->items(), 'userId'))));
        $this->getJson($timePath)->assertOk()->assertJsonPath('data.total', 27);
        $this->getJson($base.'/issues/'.$epic['id'])->assertOk()->assertJsonPath('data.spentMinutes', 79);
        $people = $this->getJson($base.'/member-options')->assertOk()->assertJsonPath('data.items.0.id', $actor['user']['id'])->json('data.items.0');
        $this->assertArrayNotHasKey('email', $people);
    }

    /**
     * 原生MySQL驗證個人查詢的聚合、欄位、時間與能力型別。
     *
     * @return void
     */
    public function test_native_mysql_personal_reads_and_version_fingerprints(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Personal SQL', 'email' => 'personal-'.Str::uuid().'@example.test', 'password' => 'mysql-test-password', 'workspaceName' => 'Personal'])->assertOk();
        $version = $this->getJson('/api/v1/personal-version')->assertOk()->json('data');
        $todo = $this->rpc('createTodo', ['content' => 'Native source'."\n\n".'**Preserve**'])->assertOk()->json('data');
        $next = $this->getJson('/api/v1/personal-version')->assertOk()->json('data');
        $this->assertNotSame($version['todos'], $next['todos']);
        $this->assertSame($next, $this->getJson('/api/v1/personal-version')->assertOk()->json('data'));
        $this->getJson('/api/v1/todos/counts')->assertOk()->assertJsonPath('data.mineOpen', 1)->assertJsonPath('data.mineClosed', 0);
        $row = $this->getJson('/api/v1/todos')->assertOk()->assertJsonCount(1, 'data.items')->json('data.items.0');
        $this->assertTrue($row['capabilities']['edit']);
        $this->assertArrayNotHasKey('content', $row);
        $this->assertArrayNotHasKey('history', $row);
        $this->getJson('/api/v1/todos/'.$todo['id'])->assertOk()->assertJsonPath('data.content', 'Native source'."\n\n".'**Preserve**');
        $this->getJson('/api/v1/todos/'.$todo['id'].'/history')->assertOk()->assertJsonPath('data.total', 1);
        $this->rpc('completeTodo', ['id' => $todo['id'], 'expectedRevision' => 1])->assertOk();
        $this->getJson('/api/v1/todos')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/todos?state=closed')->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson('/api/v1/todo-notifications/unread')->assertOk()->assertJsonPath('data.unread', 0);
        $this->getJson('/api/v1/project-lead-options')->assertOk()->assertJsonPath('data.total', 1)->assertJsonMissingPath('data.items.0.email');
        $this->getJson('/api/v1/project-options')->assertOk()->assertJsonPath('data.total', 0);
    }
}
