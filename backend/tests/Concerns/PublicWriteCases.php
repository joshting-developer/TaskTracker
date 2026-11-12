<?php

namespace Tests\Concerns;

use App\Authorization\ProjectAccess;
use App\Models\IntegrationAudit;
use App\Models\Issue;
use App\Models\PersonalAccessToken;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Opis\JsonSchema\CompliantValidator;

/** 公開寫入用真實HTTP／DB驗證冪等、關聯、權限及有界回應；不用Owner fallback。 */
trait PublicWriteCases
{
    use PersonalTokenCases;

    /**
     * 純Bearer寫入；HTTP失敗不輸出Token。
     *
     * @param  string  $plain  隔離PAT
     * @param  string  $method  HTTP方法
     * @param  string  $path  公開v1後綴
     * @param  array  $input  JSON欄位
     * @param  int  $status  預期HTTP
     * @return array 解碼回應
     */
    private function publicWrite(string $plain, string $method, string $path, array $input, int $status = 200): array
    {
        Auth::forgetGuards();
        $response = $this->json($method, '/api/public/v1'.$path, $input, ['Authorization' => 'Bearer '.$plain])->assertStatus($status)->assertCookieMissing(config('session.cookie'));
        if ($status === 200) {
            $keys = array_keys($response->json('data'));
            sort($keys);
            $this->assertSame(['id', 'identifier', 'projectId', 'revision'], $keys);
            $this->assertLessThan(400, strlen($response->getContent()));
        }
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $template = '/api/public/v1/projects/{project}/issues'.($method === 'PATCH' || str_ends_with($path, '/comments') ? '/{issue}' : '').(str_ends_with($path, '/comments') ? '/comments' : '');
        $schema = $spec['paths'][$template][strtolower($method)]['responses'][(string) $status]['content']['application/json']['schema'];
        $root = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$schema, 'components' => $spec['components']]));
        $this->assertTrue((new CompliantValidator)->validate(json_decode($response->getContent()), $root)->isValid(), '公開寫入回應不符合正式Schema');

        return $response->json();
    }

    /**
     * 登入一般測試帳號後建立隔離Project與write PAT。
     *
     * @return array 身分、專案與憑證
     */
    private function writeFixture(): array
    {
        $identity = $this->tokenRegister();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'public writes', 'code' => 'WRITE', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$identity, $project, $this->tokenIssue(['abilities' => ['write']])];
    }

    /**
     * 公開建立、修改與留言是原子操作、可安全重播，且不清空省略的欄位。
     *
     * @return void
     */
    public function test_public_create_patch_and_comment_are_atomic_replay_safe_and_do_not_clear_omitted_fields(): void
    {
        [$actor,$project,$token] = $this->writeFixture();
        $plain = $token['plainTextToken'];
        $prefix = '/projects/'.$project['id'].'/issues';
        $input = ['title' => 'first', 'description' => 'keep description', 'requestId' => (string) Str::uuid()];
        $created = $this->publicWrite($plain, 'POST', $prefix, $input)['data'];
        $count = IntegrationAudit::count();
        $this->assertEquals($created, $this->publicWrite($plain, 'POST', $prefix, $input)['data']);
        $this->assertSame($count, IntegrationAudit::count());
        $this->assertSame(1, Issue::where('public_id', $created['id'])->count());
        $this->publicWrite($plain, 'POST', $prefix, [...$input, 'title' => 'changed'], 409);
        $epic = $this->publicWrite($plain, 'POST', $prefix, ['title' => 'epic', 'type' => 'epic', 'requestId' => (string) Str::uuid()])['data'];
        $link = $this->publicWrite($plain, 'POST', $prefix, ['title' => 'linked', 'requestId' => (string) Str::uuid()])['data'];
        $patched = $this->publicWrite($plain, 'PATCH', $prefix.'/'.$created['id'], ['expectedRevision' => 1, 'epicId' => $epic['id'], 'relatedIssueIds' => [$link['id']], 'requestId' => (string) Str::uuid()])['data'];
        $patch = ['expectedRevision' => $patched['revision'], 'state' => 'inprogress', 'requestId' => (string) Str::uuid()];
        $changed = $this->publicWrite($plain, 'PATCH', $prefix.'/'.$created['id'], $patch)['data'];
        $this->assertEquals($changed, $this->publicWrite($plain, 'PATCH', $prefix.'/'.$created['id'], $patch)['data']);
        $detail = $this->getJson('/api/public/v1'.$prefix.'/'.$created['id'], ['Authorization' => 'Bearer '.$plain])->assertOk()->json('data');
        $this->assertSame('first', $detail['title']);
        $this->assertSame('keep description', $detail['description']);
        $this->assertSame($epic['id'], $detail['epicId']);
        $this->assertSame([$link['id']], $detail['relatedIssueIds']);
        $comment = ['expectedRevision' => $changed['revision'], 'content' => 'one comment', 'requestId' => (string) Str::uuid()];
        $commented = $this->publicWrite($plain, 'POST', $prefix.'/'.$created['id'].'/comments', $comment)['data'];
        $before = IntegrationAudit::count();
        $this->assertEquals($commented, $this->publicWrite($plain, 'POST', $prefix.'/'.$created['id'].'/comments', $comment)['data']);
        $this->assertSame($before, IntegrationAudit::count());
        $this->getJson('/api/public/v1'.$prefix.'/'.$created['id'].'/comments', ['Authorization' => 'Bearer '.$plain])->assertOk()->assertJsonPath('data.total', 1);
        $this->publicWrite($plain, 'PATCH', $prefix.'/'.$created['id'], ['expectedRevision' => 1, 'title' => 'stale', 'requestId' => (string) Str::uuid()], 409);
        $this->publicWrite($plain, 'PATCH', $prefix.'/'.$created['id'], ['expectedRevision' => (string) $commented['revision'], 'title' => 'bad', 'requestId' => (string) Str::uuid()], 400);
        $audit = IntegrationAudit::where('source', 'token')->latest('id')->firstOrFail();
        $this->assertSame($token['token']['id'], $audit->token_id);
        $this->assertSame($actor['user']['id'], $audit->actor_user_id);
        $this->assertSame('public.v1.addComment', $audit->operation);
        $this->assertEquals($created, $this->publicWrite($plain, 'POST', $prefix, $input)['data']); // 最新卡已改，receipt仍是原revision1
        $this->assertSame($before, IntegrationAudit::count());
    }

    /**
     * 公開寫入在重播前先檢查目前Scope、Project與關聯限制。
     *
     * @return void
     */
    public function test_public_write_enforces_current_scope_project_and_relation_constraints_before_replay(): void
    {
        [$a,$pa,$ta] = $this->writeFixture();
        $read = $this->tokenIssue();
        $path = '/projects/'.$pa['id'].'/issues';
        $input = ['title' => 'protected', 'requestId' => (string) Str::uuid()];
        $created = $this->publicWrite($ta['plainTextToken'], 'POST', $path, $input)['data'];
        $this->publicWrite($read['plainTextToken'], 'POST', $path, ['title' => 'no', 'requestId' => (string) Str::uuid()], 403);
        [$b,$pb,$tb] = $this->writeFixture();
        $this->publicWrite($tb['plainTextToken'], 'PATCH', $path.'/'.$created['id'], ['expectedRevision' => 1, 'title' => 'wrong tenant', 'requestId' => (string) Str::uuid()], 404);
        $this->publicWrite($tb['plainTextToken'], 'PATCH', '/projects/'.$pb['id'].'/issues/'.$created['id'], ['expectedRevision' => 1, 'title' => 'wrong path', 'requestId' => (string) Str::uuid()], 404);
        $member = WorkspaceMember::where('user_id', $a['user']['id'])->firstOrFail();
        $member->update(['role' => 'user']);
        $model = Project::where('public_id', $pa['id'])->firstOrFail();
        ProjectMember::create(['public_id' => ProjectAccess::memberId($pa['id'], $a['user']['id']), 'workspace_id' => $member->workspace_id, 'project_id' => $model->id, 'member_id' => $member->id, 'role_id' => 'reader']);
        $this->publicWrite($ta['plainTextToken'], 'POST', $path, $input, 403); // 舊receipt不能繞過撤權
        $member->update(['disabled_at' => now()]);
        $this->publicWrite($ta['plainTextToken'], 'POST', $path, $input, 401);
        PersonalAccessToken::whereKey($tb['token']['id'])->update(['revoked_at' => now()]);
        $this->publicWrite($tb['plainTextToken'], 'POST', '/projects/'.$pb['id'].'/issues', ['title' => 'revoked', 'requestId' => (string) Str::uuid()], 401);
    }

    /**
     * 公開修改父卡、Epic與關聯時沿用既有規則，失敗的關聯會整筆回滾。
     *
     * @return void
     */
    public function test_public_parent_epic_and_related_updates_keep_existing_rules_and_failed_links_roll_back(): void
    {
        [, $project, $token] = $this->writeFixture();
        $plain = $token['plainTextToken'];
        $path = '/projects/'.$project['id'].'/issues';
        $epic = $this->publicWrite($plain, 'POST', $path, ['title' => 'group', 'type' => 'epic', 'requestId' => (string) Str::uuid()])['data'];
        $parent = $this->publicWrite($plain, 'POST', $path, ['title' => 'parent', 'epicId' => $epic['id'], 'requestId' => (string) Str::uuid()])['data'];
        $child = $this->publicWrite($plain, 'POST', $path, ['title' => 'child', 'parentIssueId' => $parent['id'], 'requestId' => (string) Str::uuid()])['data'];
        $detail = $this->getJson('/api/public/v1'.$path.'/'.$child['id'], ['Authorization' => 'Bearer '.$plain])->assertOk()->json('data');
        $this->assertSame($epic['id'], $detail['epicId']);
        $this->assertSame($parent['id'], $detail['parentIssueId']);
        $count = IntegrationAudit::count();
        $rows = Issue::count();
        $this->publicWrite($plain, 'POST', $path, ['title' => 'too deep', 'parentIssueId' => $child['id'], 'requestId' => (string) Str::uuid()], 400);
        $this->assertSame($rows, Issue::count());
        $this->assertSame($count, IntegrationAudit::count());
        $this->publicWrite($plain, 'PATCH', $path.'/'.$parent['id'], ['expectedRevision' => $parent['revision'], 'relatedIssueIds' => [$child['id']], 'requestId' => (string) Str::uuid()]);
        $after = $this->getJson('/api/public/v1'.$path.'/'.$child['id'], ['Authorization' => 'Bearer '.$plain])->assertOk()->json('data');
        $this->assertSame([$parent['id']], $after['relatedIssueIds']);
        $requestId = (string) Str::uuid();
        $bad = ['title' => 'retry after invalid', 'parentIssueId' => 'missing', 'requestId' => $requestId];
        $this->publicWrite($plain, 'POST', $path, $bad, 404);
        // 首次失敗無receipt，可修正輸入後沿同key重試。
        $this->publicWrite($plain, 'POST', $path, ['title' => 'retry after invalid', 'requestId' => $requestId]);
    }
}
