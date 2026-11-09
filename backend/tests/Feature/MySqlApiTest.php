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
}
