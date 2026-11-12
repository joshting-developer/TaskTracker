<?php

namespace Tests\Concerns;

use App\Models\IntegrationAudit;
use App\Models\Issue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Opis\JsonSchema\CompliantValidator;

/** TT-171：外部識別upsert／查詢在真實HTTP與DB上的唯一性、revision、冪等與租戶隔離。 */
trait PublicExternalRefCases
{
    use PersonalTokenCases;

    /**
     * 純Bearer呼叫外部識別端點並對照正式OpenAPI Schema。
     *
     * @param  string  $plain  隔離PAT
     * @param  string  $method  GET或PUT
     * @param  string  $project  Project公開ID
     * @param  string  $key  `{source}/{externalId}`路徑
     * @param  array  $input  JSON欄位
     * @param  int  $status  預期HTTP
     * @return array 解碼回應
     */
    private function external(string $plain, string $method, string $project, string $key, array $input = [], int $status = 200): array
    {
        Auth::forgetGuards();
        $response = $this->json($method, '/api/public/v1/projects/'.$project.'/issues/external/'.$key, $input, ['Authorization' => 'Bearer '.$plain])->assertStatus($status)->assertCookieMissing(config('session.cookie'));
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $operation = $spec['paths']['/api/public/v1/projects/{project}/issues/external/{source}/{externalId}'][strtolower($method)];
        $schema = $operation['responses'][(string) $status]['content']['application/json']['schema'] ?? null;
        if ($schema !== null) {
            $root = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$schema, 'components' => $spec['components']]));
            $this->assertTrue((new CompliantValidator)->validate(json_decode($response->getContent()), $root)->isValid(), '外部識別回應不符合正式Schema');
        }

        return $response->json();
    }

    /**
     * 註冊隔離帳號、Project與write PAT。
     *
     * @param  string  $code  Project代碼
     * @return array{0:array,1:string} Project與PAT原文
     */
    private function externalFixture(string $code = 'EXT'): array
    {
        $this->tokenRegister();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'external '.$code, 'code' => $code, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$project, $this->tokenIssue(['abilities' => ['write']])['plainTextToken']];
    }

    /**
     * 同外部識別只建一張卡；已存在時必須帶expectedRevision；requestId重播原結果；省略欄位保留。
     *
     * @return void
     */
    public function test_external_upsert_creates_once_requires_revision_and_replays(): void
    {
        [$project, $plain] = $this->externalFixture();
        $create = ['title' => 'from tracker', 'description' => 'keep', 'requestId' => (string) Str::uuid()];
        $first = $this->external($plain, 'PUT', $project['id'], 'github/repo-12', $create)['data'];
        $this->assertSame(['id', 'projectId', 'identifier', 'revision', 'externalSource', 'externalId', 'created'], array_keys($first));
        $this->assertTrue($first['created']);
        $this->assertSame(['github', 'repo-12', 1], [$first['externalSource'], $first['externalId'], $first['revision']]);
        $audits = IntegrationAudit::count();
        $this->assertEquals($first, $this->external($plain, 'PUT', $project['id'], 'github/repo-12', $create)['data'], '同requestId重播原結果');
        $this->assertSame($audits, IntegrationAudit::count(), '重播不重複稽核');

        $this->assertSame('REVISION_CONFLICT', $this->external($plain, 'PUT', $project['id'], 'github/repo-12', ['title' => 'again', 'requestId' => (string) Str::uuid()], 409)['error']['code']);
        $this->external($plain, 'PUT', $project['id'], 'github/repo-12', ['title' => 'stale', 'expectedRevision' => 9, 'requestId' => (string) Str::uuid()], 409);
        $updated = $this->external($plain, 'PUT', $project['id'], 'github/repo-12', ['title' => 'renamed', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])['data'];
        $this->assertSame([$first['id'], false, 2], [$updated['id'], $updated['created'], $updated['revision']]);
        $this->assertSame(1, Issue::where('title', 'renamed')->count() + Issue::where('title', 'again')->count());

        $detail = $this->external($plain, 'GET', $project['id'], 'github/repo-12')['data'];
        $this->assertSame([$first['id'], 'renamed', 'keep', 2], [$detail['id'], $detail['title'], $detail['description'], $detail['revision']]);
        $this->external($plain, 'PUT', $project['id'], 'github/repo-12', [...$create, 'description' => 'other'], 409); // 同requestId不同內容
        $this->assertSame(1, DB::table('issue_external_refs')->count());
    }

    /**
     * 區分大小寫、跨Project不碰撞、跨Workspace與read Token不可用；格式錯誤400／未綁定404。
     *
     * @return void
     */
    public function test_external_keys_are_project_scoped_case_sensitive_and_isolated(): void
    {
        [$project, $plain] = $this->externalFixture('EXA');
        $other = $this->postJson('/api/rpc/createProject', ['name' => 'external second', 'code' => 'EXB', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $read = $this->tokenIssue()['plainTextToken'];
        $upper = $this->external($plain, 'PUT', $project['id'], 'jira/ABC-1', ['title' => 'upper', 'requestId' => (string) Str::uuid()])['data'];
        $lower = $this->external($plain, 'PUT', $project['id'], 'jira/abc-1', ['title' => 'lower', 'requestId' => (string) Str::uuid()])['data'];
        $elsewhere = $this->external($plain, 'PUT', $other['id'], 'jira/ABC-1', ['title' => 'other project', 'requestId' => (string) Str::uuid()])['data'];
        $this->assertTrue($upper['created'] && $lower['created'] && $elsewhere['created']);
        $this->assertCount(3, array_unique([$upper['id'], $lower['id'], $elsewhere['id']]));
        $this->assertSame('upper', $this->external($read, 'GET', $project['id'], 'jira/ABC-1')['data']['title'], 'read Token可查');
        $this->external($read, 'PUT', $project['id'], 'jira/ABC-2', ['title' => 'no', 'requestId' => (string) Str::uuid()], 403);

        $this->external($plain, 'PUT', $project['id'], 'Bad Source/x', ['title' => 'x', 'requestId' => (string) Str::uuid()], 400);
        $this->external($plain, 'PUT', $project['id'], 'jira/new-1', ['requestId' => (string) Str::uuid()], 400); // 建立時標題必填
        $this->external($plain, 'GET', $project['id'], 'jira/missing', [], 404);
        $this->external($plain, 'GET', $project['id'], 'BAD/x', [], 404);

        [, $foreign] = $this->externalFixture('EXF');
        $status = $this->json('GET', '/api/public/v1/projects/'.$project['id'].'/issues/external/jira/ABC-1', [], ['Authorization' => 'Bearer '.$foreign])->status();
        $this->assertContains($status, [403, 404], '其他Workspace的Token不能讀');
        $this->json('PUT', '/api/public/v1/projects/'.$project['id'].'/issues/external/jira/ABC-1', ['title' => 'x', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer '.$foreign])->assertStatus($status);
        $this->assertSame('upper', Issue::where('public_id', $upper['id'])->value('title'));
    }
}
