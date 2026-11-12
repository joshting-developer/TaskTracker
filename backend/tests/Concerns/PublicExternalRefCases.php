<?php

namespace Tests\Concerns;

use App\Database\DatabaseTimes;
use App\Models\IntegrationAudit;
use App\Models\Issue;
use Carbon\CarbonImmutable;
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

    /**
     * 只計本測試Project的卡片；隔離MySQL可能有其他測試資料。
     *
     * @param  string  $project  Project公開ID
     * @return int 卡片數
     */
    private function issuesIn(string $project): int
    {
        return Issue::whereHas('project', fn ($q) => $q->where('public_id', $project))->count();
    }

    /**
     * TT-172：批次呼叫並對照正式Schema。
     *
     * @param  string  $plain  隔離PAT
     * @param  string  $project  Project公開ID
     * @param  array  $input  批次JSON
     * @param  int  $status  預期HTTP
     * @return array 解碼回應
     */
    private function batchUpsert(string $plain, string $project, array $input, int $status = 200): array
    {
        Auth::forgetGuards();
        $response = $this->postJson('/api/public/v1/projects/'.$project.'/issues/external-batch', $input, ['Authorization' => 'Bearer '.$plain])->assertStatus($status);
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $schema = $spec['paths']['/api/public/v1/projects/{project}/issues/external-batch']['post']['responses'][(string) $status]['content']['application/json']['schema'];
        $root = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$schema, 'components' => $spec['components']]));
        $this->assertTrue((new CompliantValidator)->validate(json_decode($response->getContent()), $root)->isValid(), '批次回應不符合正式Schema');

        return $response->json();
    }

    /**
     * 逐項結果與失敗隔離；Epic→子卡可同批或分階段引用；逾時同requestId取回原結果；createOnly重跑不重複建卡。
     *
     * @return void
     */
    public function test_batch_reports_each_item_and_can_be_rerun_without_duplicates(): void
    {
        [$project, $plain] = $this->externalFixture('BAT');
        $first = ['requestId' => (string) Str::uuid(), 'createOnly' => true, 'items' => [
            ['source' => 'jira', 'externalId' => 'EPIC-1', 'title' => 'epic', 'type' => 'epic'],
            ['source' => 'jira', 'externalId' => 'T-1', 'title' => 'child', 'epicRef' => ['source' => 'jira', 'externalId' => 'EPIC-1']],
            ['source' => 'jira', 'externalId' => 'T-2'], // 缺title，只有這項失敗
            ['source' => 'jira', 'externalId' => 'T-3', 'title' => 'orphan', 'parentRef' => ['source' => 'jira', 'externalId' => 'NOPE']],
        ]];
        $result = $this->batchUpsert($plain, $project['id'], $first)['data'];
        $this->assertSame(['created' => 2, 'updated' => 0, 'exists' => 0, 'failed' => 2], $result['summary']);
        $this->assertSame(['created', 'created', 'failed', 'failed'], array_column($result['items'], 'status'));
        $this->assertSame('NOT_FOUND', $result['items'][3]['error']['code']);
        $this->assertSame(2, $this->issuesIn($project['id']), '失敗項目整項回滾，不留半張卡');
        $epic = $result['items'][0]['id'];
        $this->assertSame($epic, $this->external($plain, 'GET', $project['id'], 'jira/T-1')['data']['epicId']);
        $this->assertEquals($result, $this->batchUpsert($plain, $project['id'], $first)['data'], '逾時同requestId取回原逐項結果');
        $this->batchUpsert($plain, $project['id'], [...$first, 'createOnly' => false], 409);

        // 換新requestId補跑：已建立者exists不更新，修正後的項目建立，子卡可引用前一批的Epic。
        $rerun = ['requestId' => (string) Str::uuid(), 'createOnly' => true, 'items' => [
            ['source' => 'jira', 'externalId' => 'EPIC-1', 'title' => 'epic renamed', 'type' => 'epic'],
            ['source' => 'jira', 'externalId' => 'T-2', 'title' => 'fixed', 'epicRef' => ['source' => 'jira', 'externalId' => 'EPIC-1']],
        ]];
        $again = $this->batchUpsert($plain, $project['id'], $rerun)['data'];
        $this->assertSame(['exists', 'created'], array_column($again['items'], 'status'));
        $this->assertSame($epic, $again['items'][0]['id']);
        $this->assertSame('epic', Issue::where('public_id', $epic)->value('title'), 'createOnly不更新既有卡');
        $this->assertSame(3, $this->issuesIn($project['id']));

        // 非createOnly：缺revision的項目失敗，帶revision的項目更新。
        $child = $this->external($plain, 'GET', $project['id'], 'jira/T-1')['data'];
        $update = $this->batchUpsert($plain, $project['id'], ['requestId' => (string) Str::uuid(), 'items' => [
            ['source' => 'jira', 'externalId' => 'T-1', 'title' => 'child updated', 'expectedRevision' => $child['revision']],
            ['source' => 'jira', 'externalId' => 'T-2', 'title' => 'no revision'],
        ]])['data'];
        $this->assertSame(['updated', 'failed'], array_column($update['items'], 'status'));
        $this->assertSame('REVISION_CONFLICT', $update['items'][1]['error']['code']);
        $this->assertSame('child updated', Issue::where('public_id', $child['id'])->value('title'));
    }

    /**
     * 結構錯誤整批400、read Token 403，不寫入任何卡片。
     *
     * @return void
     */
    public function test_batch_rejects_bad_structure_and_read_tokens(): void
    {
        [$project, $plain] = $this->externalFixture('BAX');
        $item = ['source' => 'jira', 'externalId' => 'A-1', 'title' => 'a'];
        $this->batchUpsert($plain, $project['id'], ['requestId' => (string) Str::uuid(), 'items' => [$item, $item]], 400);
        $this->batchUpsert($plain, $project['id'], ['requestId' => (string) Str::uuid(), 'items' => array_map(fn ($n) => [...$item, 'externalId' => 'A-'.$n], range(1, 51))], 400);
        $this->batchUpsert($plain, $project['id'], ['requestId' => (string) Str::uuid(), 'items' => [[...$item, 'epicRef' => ['source' => 'jira', 'externalId' => 'E'], 'epicId' => 'x']]], 400);
        $this->batchUpsert($plain, $project['id'], ['requestId' => (string) Str::uuid(), 'items' => []], 400);
        $read = $this->tokenIssue()['plainTextToken'];
        $this->batchUpsert($read, $project['id'], ['requestId' => (string) Str::uuid(), 'items' => [$item]], 403);
        $this->assertSame(0, $this->issuesIn($project['id']));
    }

    /**
     * TT-173：增量項目附外部識別；下一輪水位往前退settle秒，邊界內較晚提交的寫入在下一輪仍讀得到。
     *
     * @return void
     */
    public function test_issue_changes_include_external_refs_and_settled_watermark_catches_late_commits(): void
    {
        config(['tasktracker.sync.settle_seconds' => 10]);
        [$project, $plain] = $this->externalFixture('SYN');
        $linked = $this->external($plain, 'PUT', $project['id'], 'github/repo-1', ['title' => 'linked', 'requestId' => (string) Str::uuid()])['data'];
        Auth::forgetGuards();
        $plainCard = $this->postJson('/api/public/v1/projects/'.$project['id'].'/issues', ['title' => 'plain', 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer '.$plain])->assertOk()->json('data');
        $read = fn (array $query) => $this->getJson('/api/public/v1/projects/'.$project['id'].'/issue-changes?'.http_build_query($query), ['Authorization' => 'Bearer '.$plain])->assertOk()->json('data');

        $round = $read(['perPage' => 100]);
        $items = collect($round['items'])->keyBy('id');
        $this->assertSame([['source' => 'github', 'externalId' => 'repo-1']], $items[$linked['id']]['externalRefs']);
        $this->assertSame([], $items[$plainCard['id']]['externalRefs']);
        $until = CarbonImmutable::parse($round['syncUntil']);
        $this->assertTrue($until->lessThan(CarbonImmutable::parse($items[$linked['id']]['updatedAt'])), '水位比本輪最新項目早，留有settle緩衝');

        // 模擬較晚提交：時間戳落在水位與上一輪讀取時間之間的寫入，上一輪看不到，下一輪仍會讀到。
        $late = Issue::where('public_id', $plainCard['id'])->firstOrFail()->replicate();
        $late->public_id = (string) Str::uuid();
        $late->identifier = 'SYN-99';
        $late->updated_at = DatabaseTimes::store($until->addSeconds(3)->toISOString());
        $late->save();
        $next = collect($read(['perPage' => 100, 'updatedSince' => $round['syncUntil']])['items'])->pluck('id');
        $this->assertContains($late->public_id, $next->all(), '邊界內較晚提交不遺漏');
        $this->assertContains($linked['id'], $next->all(), '重讀緩衝內項目，由客戶端以id／revision去重');
    }
}
