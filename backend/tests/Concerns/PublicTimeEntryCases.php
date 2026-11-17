<?php

namespace Tests\Concerns;

use App\Models\Issue;
use App\Models\PersonalAccessToken;
use App\Models\ProjectMember;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\CompliantValidator;

/** 公開工時使用隔離帳號與真實HTTP, 和Session資料讀回交叉核對 */
trait PublicTimeEntryCases
{
    /** 建立本人、Project、Issue與讀寫PAT
     * @return array 合成fixture
     */
    private function publicTimeFixture(): array
    {
        $actor = $this->postJson('/api/auth/register', ['name' => 'Time writer', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-public-time', 'workspaceName' => 'Time scope'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Time project', 'code' => 'PTIME', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Tracked work', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $headers = [];
        foreach (['write', 'read'] as $ability) {
            $plain = $this->postJson('/api/v1/personal-tokens', ['name' => $ability, 'abilities' => [$ability], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
            $headers[$ability] = ['Authorization' => 'Bearer '.$plain];
        }

        return [$actor, $project, $issue, $headers, '/api/public/v1/projects/'.$project['id'].'/time-entries'];
    }

    /** 真實HTTP回應對照正式OpenAPI, 不在失敗訊息輸出Token
     * @param  TestResponse  $response  實際回應
     * @param  string  $path  規格路徑
     * @param  string  $method  HTTP方法
     * @return void
     */
    private function publicTimeSchema(TestResponse $response, string $path, string $method): void
    {
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $schema = $spec['paths'][$path][$method]['responses'][(string) $response->status()]['content']['application/json']['schema'];
        $root = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$schema, 'components' => $spec['components']], JSON_THROW_ON_ERROR));
        $this->assertTrue((new CompliantValidator)->validate(json_decode($response->getContent()), $root)->isValid(), '公開工時回應與正式Schema不符');
    }

    /** 建立欄位不接受作者, 工作日沿台北日期
     * @param  array  $issue  目標Issue
     * @return array 寫入資料
     */
    private function publicTimeInput(array $issue): array
    {
        return ['requestId' => (string) Str::uuid(), 'issueId' => $issue['id'], 'workDate' => now('Asia/Taipei')->toDateString(), 'minutes' => 30, 'note' => 'Synthetic work'];
    }

    /** 新增、更新與作廢同網頁資料, 重試不重複計時, 舊revision被拒
     * @return void
     */
    public function test_public_time_lifecycle_replay_and_session_totals(): void
    {
        [, $project, $issue, $headers, $base] = $this->publicTimeFixture();
        $input = $this->publicTimeInput($issue);
        $created = $this->postJson($base, $input, $headers['write'])->assertOk();
        $this->publicTimeSchema($created, '/api/public/v1/projects/{project}/time-entries', 'post');
        $entry = $created->json('data');
        $this->publicTimeSchema($this->getJson($base, $headers['read'])->assertOk(), '/api/public/v1/projects/{project}/time-entries', 'get');
        $this->assertSame(1, $entry['revision']);
        $replayed = $this->postJson($base, $input, $headers['write'])->assertOk()->json('data');
        ksort($entry);
        ksort($replayed);
        $this->assertSame($entry, $replayed);
        $this->postJson($base, [...$input, 'minutes' => 31], $headers['write'])->assertStatus(409);
        $this->getJson($base, $headers['read'])->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.note', 'Synthetic work')->assertJsonMissingPath('data.items.0.history');
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'].'/time')->assertOk()->assertJsonPath('data.items.0.id', $entry['id']);
        $edit = ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1, 'workDate' => $input['workDate'], 'minutes' => 60, 'note' => 'Changed'];
        $this->patchJson($base.'/'.$entry['id'], $edit, $headers['write'])->assertOk()->assertJsonPath('data.revision', 2);
        $this->patchJson($base.'/'.$entry['id'], $edit, $headers['write'])->assertOk()->assertJsonPath('data.revision', 2);
        $this->patchJson($base.'/'.$entry['id'], [...$edit, 'requestId' => (string) Str::uuid()], $headers['write'])->assertStatus(409);
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'])->assertOk()->assertJsonPath('data.spentMinutes', 60);
        $void = ['requestId' => (string) Str::uuid(), 'expectedRevision' => 2];
        $this->postJson($base.'/'.$entry['id'].'/void', $void, $headers['write'])->assertOk()->assertJsonPath('data.revision', 3);
        $this->postJson($base.'/'.$entry['id'].'/void', $void, $headers['write'])->assertOk()->assertJsonPath('data.revision', 3);
        $this->getJson($base, $headers['read'])->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'])->assertOk()->assertJsonPath('data.spentMinutes', 0);
        $this->assertSame(1, TimeEntry::where('issue_id', Issue::where('public_id', $issue['id'])->value('id'))->count());
        $this->patchJson($base.'/'.$entry['id'], [...$edit, 'requestId' => (string) Str::uuid(), 'expectedRevision' => 3], $headers['write'])->assertStatus(403);
    }

    /** 嚴格日期與分鐘、Scope、未知欄位及每日上限皆沿既有規則
     * @return void
     */
    public function test_public_time_validation_scope_daily_limit_and_path_guards(): void
    {
        [, $project, $issue, $headers, $base] = $this->publicTimeFixture();
        $input = $this->publicTimeInput($issue);
        $this->postJson($base, $input)->assertStatus(401);
        $this->postJson($base, $input, $headers['read'])->assertStatus(403);
        foreach ([['minutes' => 0], ['minutes' => -1], ['minutes' => 1441], ['minutes' => '30'], ['workDate' => '2026-02-30'], ['workDate' => now('Asia/Taipei')->addDay()->toDateString()], ['userId' => 'other'], ['projectId' => $project['id']], ['requestId' => 'not-uuid']] as $bad) {
            $this->postJson($base, [...$input, ...$bad], $headers['write'])->assertStatus(400);
        }
        $other = $this->postJson('/api/rpc/createProject', ['name' => 'Other project', 'code' => 'OTHER', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $otherBase = '/api/public/v1/projects/'.$other['id'].'/time-entries';
        $this->postJson($otherBase, $input, $headers['write'])->assertStatus(404);
        $entry = $this->postJson($base, [...$input, 'minutes' => 1440], $headers['write'])->assertOk()->json('data');
        $this->postJson($base, [...$input, 'requestId' => (string) Str::uuid(), 'minutes' => 1], $headers['write'])->assertStatus(400);
        $this->postJson($otherBase.'/'.$entry['id'].'/void', ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1], $headers['write'])->assertStatus(404);
        foreach (['?perPage=101', '?page=101', '?from=2024-01-01&to=2026-01-01', '?from=2026-12-01&to=2026-01-01', '?userId=other'] as $query) {
            $this->getJson($base.$query, $headers['read'])->assertStatus(400);
        }
        $this->assertSame(1, TimeEntry::where('issue_id', Issue::where('public_id', $issue['id'])->value('id'))->count());
    }

    /** 作者限定、單卡可見性、跨租戶與撤權後重播保持相同權限邊界
     * @return void
     */
    public function test_public_time_author_scope_and_revocation_before_replay(): void
    {
        [$actor, $project, $issue, $headers, $base] = $this->publicTimeFixture();
        $input = $this->publicTimeInput($issue);
        $created = $this->postJson($base, $input, $headers['write'])->assertOk();
        $this->publicTimeSchema($created, '/api/public/v1/projects/{project}/time-entries', 'post');
        $entry = $created->json('data');
        $invite = $this->postJson('/api/rpc/inviteMember', ['email' => Str::uuid().'@example.test', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        $other = $this->postJson('/api/auth/register', ['name' => 'Other author', 'email' => $invite['email'], 'password' => 'isolated-public-time', 'inviteToken' => $invite['token']])->assertOk()->json('data');
        $plain = $this->postJson('/api/v1/personal-tokens', ['name' => 'other author', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $memberHeaders = ['Authorization' => 'Bearer '.$plain];
        $this->actingAs(User::findOrFail($actor['user']['id']))->withSession(['workspace_id' => $actor['workspace']['id'], 'session_epoch' => 0]);
        $acl = $this->postJson('/api/rpc/getProjectMembers', ['projectId' => $project['id']])->assertOk()->json('data');
        $this->postJson('/api/rpc/setProjectMember', ['projectId' => $project['id'], 'userId' => $other['user']['id'], 'roleId' => 'developer', 'expectedRevision' => $acl['revision'], 'requestId' => (string) Str::uuid()])->assertOk();
        $otherInput = [...$input, 'requestId' => (string) Str::uuid()];
        $this->postJson($base, $otherInput, $memberHeaders)->assertOk();
        $this->getJson($base, $headers['read'])->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.userId', $actor['user']['id']);
        $this->getJson('/api/public/v1/projects/'.$project['id'].'/issues/'.$issue['id'].'/time-entries', $headers['read'])->assertOk()->assertJsonPath('data.total', 2);
        $this->postJson($base.'/'.$entry['id'].'/void', ['expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $memberHeaders)->assertStatus(403);
        $membership = WorkspaceMember::where('user_id', $other['user']['id'])->firstOrFail();
        ProjectMember::where('member_id', $membership->id)->update(['removed_at' => now()]);
        $this->postJson($base, $otherInput, $memberHeaders)->assertStatus(404);
        $tokenId = explode('|', substr($headers['read']['Authorization'], 7))[0];
        PersonalAccessToken::whereKey($tokenId)->update(['revoked_at' => now()]);
        $this->getJson($base, $headers['read'])->assertStatus(401);
        WorkspaceMember::where('user_id', $actor['user']['id'])->update(['disabled_at' => now()]);
        $this->postJson($base, $input, $headers['write'])->assertStatus(401);
        $this->getJson($base, $headers['read'])->assertStatus(401);
        WorkspaceMember::where('user_id', $actor['user']['id'])->update(['disabled_at' => null]);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/register', ['name' => 'Foreign tenant', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-public-time', 'workspaceName' => 'Foreign'])->assertOk();
        $foreign = $this->postJson('/api/v1/personal-tokens', ['name' => 'foreign', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $this->getJson($base, ['Authorization' => 'Bearer '.$foreign])->assertStatus(404);
        $this->postJson($base, $input, ['Authorization' => 'Bearer '.$foreign])->assertStatus(404);
    }

    /** 日期與單卡篩選在SQL執行, 分頁穩定且不回傳歷史
     * @return void
     */
    public function test_public_time_pagination_and_date_issue_filters(): void
    {
        [, $project, $issue, $headers, $base] = $this->publicTimeFixture();
        $input = $this->publicTimeInput($issue);
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->postJson($base, [...$input, 'requestId' => (string) Str::uuid()], $headers['write'])->assertOk()->json('data.id');
        }
        $found = [];
        for ($page = 1; $page <= 3; $page++) {
            $found[] = $this->getJson($base.'?perPage=1&page='.$page.'&issueId='.$issue['id'].'&from='.$input['workDate'].'&to='.$input['workDate'], $headers['read'])->assertOk()->assertJsonPath('data.total', 3)->assertJsonCount(1, 'data.items')->assertJsonMissingPath('data.items.0.history')->json('data.items.0.id');
        }
        $this->assertEqualsCanonicalizing($ids, $found);
        $this->getJson($base.'?issueId=unrelated', $headers['read'])->assertOk()->assertJsonPath('data.total', 0);
        $date = now('Asia/Taipei')->subDay()->toDateString();
        $this->getJson($base.'?from='.$date.'&to='.$date, $headers['read'])->assertOk()->assertJsonPath('data.total', 0);
    }
}
