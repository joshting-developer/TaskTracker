<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\MySqlTestCase;

/** 真實InnoDB驗證資料型別、DTO、關聯、receipt及作者／租戶邊界。 */
class MySqlApiTest extends MySqlTestCase
{
    use DatabaseTransactions;

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
}
