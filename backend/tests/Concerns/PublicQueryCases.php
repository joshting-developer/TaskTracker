<?php

namespace Tests\Concerns;

use App\Authorization\ProjectAccess;
use App\Models\IntegrationAudit;
use App\Models\PersonalAccessToken;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectStatus;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Opis\JsonSchema\CompliantValidator;

/** memory與MySQL共用公開Bearer HTTP；無業務mock，檢查正式Schema與實際讀取範圍。 */
trait PublicQueryCases
{
    use PersonalTokenCases;

    /**
     * 以純Bearer讀取並驗證正式Schema，不將秘密回應輸出到日誌。
     *
     * @param  string  $plain  隔離Token
     * @param  string  $url  實際URL
     * @param  string  $template  正式OpenAPI path
     * @param  int  $status  預期狀態
     * @return array 安全解碼回應
     */
    private function publicRead(string $plain, string $url, string $template, int $status = 200): array
    {
        Auth::forgetGuards();
        $response = $this->getJson('/api/public/v1'.$url, ['Authorization' => 'Bearer '.$plain])->assertStatus($status)->assertCookieMissing(config('session.cookie'));
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $schema = $spec['paths']['/api/public/v1'.$template]['get']['responses'][(string) $status]['content']['application/json']['schema'];
        $root = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$schema, 'components' => $spec['components']]));
        $this->assertTrue((new CompliantValidator)->validate(json_decode($response->getContent()), $root)->isValid(), '公開回應不符合正式Schema');

        return $response->json();
    }

    /**
     * 建立不同Project／Issue與活動的人造資料。
     *
     * @return array 身分、Project、Issue、Token
     */
    private function publicFixture(): array
    {
        $identity = $this->tokenRegister();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'public example', 'code' => 'PUB', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'target', 'description' => 'detail-marker', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/rpc/addComment', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'content' => 'comment-marker', 'requestId' => (string) Str::uuid()])->assertOk();

        return [$identity, $project, $issue, $this->tokenIssue()];
    }

    /**
     * 公開查詢共用既有投影與篩選，read Token只能讀。
     *
     * @return void
     */
    public function test_public_queries_share_projection_filters_and_read_only_scope(): void
    {
        [$identity, $project, $issue, $token] = $this->publicFixture();
        $plain = $token['plainTextToken'];
        $auditCount = IntegrationAudit::count();
        $projects = $this->publicRead($plain, '/projects?perPage=1', '/projects')['data'];
        $this->assertCount(1, $projects['items']);
        $this->assertArrayNotHasKey('issues', $projects['items'][0]);
        $prefix = '/projects/'.$project['id'];
        $metadata = $this->publicRead($plain, $prefix, '/projects/{project}')['data'];
        $this->assertSame(['open', 'inprogress', 'closed'], array_column($metadata['statuses'], 'id'));
        $this->assertArrayNotHasKey('members', $metadata);
        $states = $this->publicRead($plain, $prefix.'/statuses', '/projects/{project}/statuses')['data'];
        $this->assertSame($metadata['statuses'], $states['items']);
        $members = $this->publicRead($plain, $prefix.'/members', '/projects/{project}/members')['data'];
        foreach ($members['items'] as $member) {
            $this->assertSame(['id', 'name'], array_keys($member));
        }
        $page = $this->publicRead($plain, $prefix.'/issues?identifier='.urlencode($issue['identifier']), '/projects/{project}/issues')['data'];
        $this->assertSame(1, $page['total']);
        $this->assertArrayNotHasKey('description', $page['items'][0]);
        $this->assertArrayNotHasKey('comments', $page['items'][0]);
        $this->assertArrayNotHasKey('spentMinutes', $page['items'][0]);
        $detail = $this->publicRead($plain, $prefix.'/issues/'.$issue['id'], '/projects/{project}/issues/{issue}')['data'];
        $this->assertSame('detail-marker', $detail['description']);
        $this->assertArrayHasKey('relatedIssueIds', $detail);
        $this->assertGreaterThanOrEqual($issue['revision'], $detail['revision']);
        $this->assertArrayNotHasKey('history', $detail);
        $comments = $this->publicRead($plain, $prefix.'/issues/'.$issue['id'].'/comments', '/projects/{project}/issues/{issue}/comments')['data'];
        $this->assertSame('comment-marker', $comments['items'][0]['content']);
        $this->assertSame(0, $this->publicRead($plain, $prefix.'/issues?state[]=closed', '/projects/{project}/issues')['data']['total']);
        $this->assertSame(1, $this->publicRead($plain, $prefix.'/issues?state[]=open&scope=all&type=task&sort=due', '/projects/{project}/issues')['data']['total']);
        foreach (['perPage=101', 'page=0', 'sort=wrong', 'state=open', 'page=999'] as $invalid) {
            $this->publicRead($plain, $prefix.'/issues?'.$invalid, '/projects/{project}/issues', 400);
        }
        $this->assertSame([], $this->publicRead($plain, $prefix.'/issues?page=501', '/projects/{project}/issues')['data']['items']);
        $this->assertSame(0, $this->publicRead($plain, '/projects?search=%25', '/projects')['data']['total']);
        $this->assertSame(1, $this->publicRead($plain, $prefix.'/issues?identifier='.urlencode($issue['identifier']).'&workspaceId=forged&actor=forged', '/projects/{project}/issues')['data']['total']);
        $this->postJson('/api/public/v1/projects/'.$project['id'].'/issues', ['title' => 'no write', 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer '.$plain])->assertStatus(403);

        $this->assertSame($auditCount, IntegrationAudit::count());
        $this->assertNull(PersonalAccessToken::findOrFail($token['token']['id'])->last_used_at);
    }

    /**
     * 公開查詢每次重驗工作區與Project資格；瀏覽器Session不能當作Bearer認證的退路。
     *
     * @return void
     */
    public function test_public_queries_revalidate_workspace_project_and_session_is_not_an_authentication_fallback(): void
    {
        [$a, $pa, $ia, $ta] = $this->publicFixture();
        [$b, $pb, $ib, $tb] = $this->publicFixture();
        $prefix = '/projects/'.$pa['id'];
        $this->publicRead($tb['plainTextToken'], $prefix, '/projects/{project}', 404);
        $this->publicRead($ta['plainTextToken'], $prefix.'/issues/'.$ib['id'], '/projects/{project}/issues/{issue}', 404);
        $this->assertSame($a['user']['id'], $this->publicRead($ta['plainTextToken'], '/me', '/me')['data']['userId']);
        $member = WorkspaceMember::where('user_id', $a['user']['id'])->firstOrFail();
        $member->update(['role' => 'user']);
        $this->assertSame(0, $this->publicRead($ta['plainTextToken'], '/projects', '/projects')['data']['total']);
        $this->publicRead($ta['plainTextToken'], $prefix, '/projects/{project}', 404);
        $project = Project::where('public_id', $pa['id'])->firstOrFail();
        ProjectMember::create(['public_id' => ProjectAccess::memberId($pa['id'], $a['user']['id']), 'workspace_id' => $member->workspace_id, 'project_id' => $project->id, 'member_id' => $member->id, 'role_id' => 'reader']);
        $this->assertSame(1, $this->publicRead($ta['plainTextToken'], '/projects', '/projects')['data']['total']);
        $detail = $this->publicRead($ta['plainTextToken'], $prefix.'/issues/'.$ia['id'], '/projects/{project}/issues/{issue}')['data'];
        $this->assertArrayHasKey('spentMinutes', $detail, 'TT-317: card readers can see card-scoped time');
        $this->assertArrayHasKey('time', $detail['activityCounts']);
        ProjectMember::where('member_id', $member->id)->update(['removed_at' => now()]);
        $this->publicRead($ta['plainTextToken'], $prefix, '/projects/{project}', 404);
        $member->update(['disabled_at' => now()]);
        $this->publicRead($ta['plainTextToken'], '/projects', '/projects', 401);
        PersonalAccessToken::whereKey($tb['token']['id'])->update(['revoked_at' => now()]);
        $this->publicRead($tb['plainTextToken'], '/projects', '/projects', 401);
        Auth::forgetGuards();
        $this->getJson('/api/public/v1/projects')->assertStatus(401);
    }

    /**
     * 公開查詢的資料範圍仍限定在目前Project，不受無關內容影響。
     *
     * @return void
     */
    public function test_public_query_data_bounds_remain_project_scoped_with_unrelated_content(): void
    {
        [$identity,$project,$issue,$token] = $this->publicFixture();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'row-'.$i, 'requestId' => (string) Str::uuid()])->assertOk();
        }
        $other = $this->postJson('/api/rpc/createProject', ['name' => 'unrelated', 'code' => 'OTHER', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/rpc/createIssue', ['projectId' => $other['id'], 'title' => 'unrelated-marker', 'description' => str_repeat('unrelated', 300), 'requestId' => (string) Str::uuid()])->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $data = $this->publicRead($token['plainTextToken'], '/projects/'.$project['id'].'/issues?perPage=2', '/projects/{project}/issues')['data'];
            $this->assertSame(6, $data['total']);
            $this->assertCount(2, $data['items']);
            $this->assertSame([$project['id']], array_values(array_unique(array_column($data['items'], 'projectId'))));
            $sql = implode('\n', array_column(DB::getQueryLog(), 'query'));
            $this->assertStringNotContainsString('issue_comments', $sql);
            $this->assertStringNotContainsString('audit_events', $sql);
            $this->assertStringNotContainsString('time_entries', $sql);
            $this->assertStringNotContainsString('description', $sql);
            $this->assertStringContainsString('limit 2', $sql);
            DB::flushQueryLog();
            $this->publicRead($token['plainTextToken'], '/projects/'.$project['id'].'/statuses', '/projects/{project}/statuses');
            $statusSql = implode('\n', array_column(DB::getQueryLog(), 'query'));
            $this->assertStringNotContainsString('description', $statusSql);
            $this->assertStringNotContainsString('issues', $statusSql);
            $this->assertStringContainsString('limit 21', $statusSql);

        } finally {
            DB::disableQueryLog();
        }
    }

    /**
     * 狀態候選超過上限時明確拒絕，不靜默截斷。
     *
     * @return void
     */
    public function test_status_candidates_reject_legacy_overflow_instead_of_silently_truncating(): void
    {
        [, $project, , $token] = $this->publicFixture();
        $model = Project::where('public_id', $project['id'])->firstOrFail();
        $template = ProjectStatus::where('project_id', $model->id)->firstOrFail();
        for ($i = 0; $i < 18; $i++) {
            $copy = $template->replicate();
            $copy->code = 'extra'.$i;
            $copy->position = $i + 3;
            $copy->save();
        }
        $this->publicRead($token['plainTextToken'], '/projects/'.$project['id'].'/statuses', '/projects/{project}/statuses', 409);
    }
}
