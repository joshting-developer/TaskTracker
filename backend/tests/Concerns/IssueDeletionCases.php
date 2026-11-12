<?php

namespace Tests\Concerns;

use App\Models\Board;
use App\Models\Issue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** TT-250：永久刪卡的版本／重播、阻擋條件、連帶清除與墓碑；SQLite與獨立MySQL共用。 */
trait IssueDeletionCases
{
    /**
     * 經正式Session RPC送出並補requestId。
     *
     * @param  string  $operation  已註冊的RPC操作
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse 真實HTTP回應
     */
    private function deletionRpc(string $operation, array $input = []): TestResponse
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 刪卡清除留言／關聯／看板收錄／通知／歷史並解除待辦連結，關聯端與看板revision+1，留下墓碑；同requestId重播回原結果。
     *
     * @return void
     */
    public function test_delete_issue_cleans_dependents_and_replays(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Deleter', 'email' => 'deleter-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Delete'])->assertOk();
        $project = $this->deletionRpc('createProject', ['name' => 'Delete', 'code' => 'DEL'])->assertOk()->json('data');
        $todo = $this->deletionRpc('createTodo', ['title' => '轉成卡片'])->assertOk()->json('data');
        $this->deletionRpc('convertTodo', ['id' => $todo['id'], 'expectedRevision' => 1, 'issue' => ['projectId' => $project['id']]])->assertOk();
        $target = Issue::orderByDesc('id')->firstOrFail();
        $peer = $this->deletionRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Peer'])->assertOk()->json('data');
        $updated = $this->deletionRpc('updateIssue', ['id' => $target->public_id, 'expectedRevision' => (int) $target->revision, 'relatedIssueIds' => [$peer['id']], 'assignee' => ''])->assertOk()->json('data');
        $updated = $this->deletionRpc('addComment', ['id' => $target->public_id, 'expectedRevision' => $updated['revision'], 'content' => '留言內容'])->assertOk()->json('data');
        $board = $this->deletionRpc('createBoard', ['projectId' => $project['id'], 'name' => 'Board', 'selectedIssueIds' => [$target->public_id, $peer['id']]])->assertOk()->json('data');
        $peerRevision = $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$peer['id'])->assertOk()->json('data.revision');
        $boardRevision = Board::where('public_id', $board['id'])->value('revision');

        $this->deletionRpc('deleteIssue', ['projectId' => $project['id'], 'id' => $target->public_id, 'expectedRevision' => $updated['revision'] - 1])->assertStatus(409);
        $input = ['projectId' => $project['id'], 'id' => $target->public_id, 'expectedRevision' => $updated['revision'], 'requestId' => (string) Str::uuid()];
        $result = $this->postJson('/api/rpc/deleteIssue', $input)->assertOk()->json('data');
        $this->assertEquals(['id' => $target->public_id, 'projectId' => $project['id'], 'identifier' => $target->identifier, 'revision' => $updated['revision'] + 1], $result);
        $this->assertEquals($result, $this->postJson('/api/rpc/deleteIssue', $input)->assertOk()->json('data'));

        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$target->public_id)->assertStatus(404);
        $this->assertSame(0, Issue::where('id', $target->id)->count());
        foreach (['issue_comments' => 'issue_id', 'issue_relations' => 'related_issue_id', 'board_issues' => 'issue_id', 'issue_notifications' => 'issue_id'] as $table => $column) {
            $this->assertSame(0, DB::table($table)->where($column, $target->id)->count(), $table);
        }
        $this->assertSame(0, DB::table('audit_events')->where('entity_type', 'issues')->where('entity_id', $target->id)->count());
        $peerAfter = $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$peer['id'])->assertOk()->json('data');
        $this->assertSame([], $peerAfter['relatedIssueIds']);
        $this->assertSame($peerRevision + 1, $peerAfter['revision']);
        $this->assertSame($boardRevision + 1, Board::where('public_id', $board['id'])->value('revision'));
        $this->getJson('/api/v1/projects/'.$project['id'].'/boards/'.$board['id'])->assertOk()->assertJsonPath('data.selectedIssueIds', [$peer['id']]);
        $this->assertNull(DB::table('todos')->where('public_id', $todo['id'])->value('converted_issue_id'));
        $this->getJson('/api/v1/todos/'.$todo['id'])->assertOk();
        $tombstone = json_decode(DB::table('audit_events')->where('entity_type', 'projects')->where('entity_id', $target->project_id)->where('stream', 'issueDeletions')->value('event_data'), true);
        $this->assertSame([$target->public_id, $target->identifier], [$tombstone['issueId'], $tombstone['identifier']]);
        $this->assertArrayNotHasKey('description', $tombstone);
        $this->getJson('/api/v1/projects/'.$project['id'])->assertOk();
    }

    /**
     * 有工時（含作廢）、Epic所屬卡或子任務時拒絕刪除且不改任何資料。
     *
     * @return void
     */
    public function test_delete_issue_blocks_time_and_children(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Guard', 'email' => 'guard-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Guard'])->assertOk();
        $project = $this->deletionRpc('createProject', ['name' => 'Guard', 'code' => 'GUARD'])->assertOk()->json('data');
        $epic = $this->deletionRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Epic', 'type' => 'epic'])->assertOk()->json('data');
        $member = $this->deletionRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Member', 'epicId' => $epic['id']])->assertOk()->json('data');
        $child = $this->deletionRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Child', 'parentIssueId' => $member['id']])->assertOk()->json('data');
        $timed = $this->deletionRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Timed'])->assertOk()->json('data');
        $entry = $this->deletionRpc('logTime', ['issueId' => $timed['id'], 'workDate' => now('Asia/Taipei')->toDateString(), 'minutes' => 15])->assertOk()->json('data');
        $this->deletionRpc('voidTime', ['id' => $entry['id'], 'expectedRevision' => $entry['revision']])->assertOk();
        $before = Issue::count();
        foreach ([$epic, $member, $timed] as $issue) {
            $revision = $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'])->assertOk()->json('data.revision');
            $this->deletionRpc('deleteIssue', ['projectId' => $project['id'], 'id' => $issue['id'], 'expectedRevision' => $revision])->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');
        }
        $this->assertSame($before, Issue::count());
        $this->deletionRpc('deleteIssue', ['projectId' => $project['id'], 'id' => $child['id'], 'expectedRevision' => $child['revision']])->assertOk();
    }

    /**
     * 公開API以DELETE刪卡，需expectedRevision／requestId，跨Project路徑回404，重播回原結果。
     *
     * @return void
     */
    public function test_public_api_deletes_issue(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Public', 'email' => 'public-del-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Public'])->assertOk();
        $project = $this->deletionRpc('createProject', ['name' => 'Public', 'code' => 'PUBDEL'])->assertOk()->json('data');
        $other = $this->deletionRpc('createProject', ['name' => 'Other', 'code' => 'OTHDEL'])->assertOk()->json('data');
        $issue = $this->deletionRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Remove me'])->assertOk()->json('data');
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'tool', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $headers = ['Authorization' => 'Bearer '.$token];
        $path = fn (string $projectId) => '/api/public/v1/projects/'.$projectId.'/issues/'.$issue['id'];
        $this->json('DELETE', $path($project['id']), ['expectedRevision' => 1], $headers)->assertStatus(400);
        $this->json('DELETE', $path($other['id']), ['expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $headers)->assertStatus(404);
        $input = ['expectedRevision' => 1, 'requestId' => (string) Str::uuid()];
        $result = $this->json('DELETE', $path($project['id']), $input, $headers)->assertOk()->json('data');
        $this->assertSame([$issue['id'], 2], [$result['id'], $result['revision']]);
        $this->assertEquals($result, $this->json('DELETE', $path($project['id']), $input, $headers)->assertOk()->json('data'));
        $this->getJson('/api/public/v1/projects/'.$project['id'].'/issues/'.$issue['id'], $headers)->assertStatus(404);
        $deletions = $this->getJson('/api/public/v1/projects/'.$project['id'].'/issue-deletions?limit=1', $headers)->assertOk()->json('data');
        $this->assertSame([$issue['id'], $issue['identifier']], [$deletions['items'][0]['issueId'], $deletions['items'][0]['identifier']]);
        $this->assertSame(['issueId', 'identifier', 'deletedAt'], array_keys($deletions['items'][0]));
        $this->assertNull($deletions['nextAfter']);
        $this->getJson('/api/public/v1/projects/'.$other['id'].'/issue-deletions', $headers)->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/public/v1/projects/'.$project['id'].'/issue-deletions?limit=101', $headers)->assertStatus(400);
    }
}
