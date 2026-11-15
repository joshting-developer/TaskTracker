<?php

namespace Tests\Concerns;

use App\Authorization\ProjectAccess;
use App\Models\IntegrationAudit;
use App\Models\ProjectTag;
use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Opis\JsonSchema\CompliantValidator;

/** Tags 使用真 HTTP 與隔離資料庫驗證, 不以 mock 取代權限或外鍵 */
trait ProjectTagCases
{
    /** 建立獨立合成帳號、工作區與專案
     *
     * @return array{array,array,string} 身分、專案與公開路徑 */
    private function tagFixture(): array
    {
        Auth::forgetGuards();
        $identity = $this->postJson('/api/auth/register', ['name' => 'Tag owner', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-tag-password', 'workspaceName' => 'Tags'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Tag project', 'code' => 'TAGS', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$identity, $project, '/api/v1/projects/'.$project['id']];
    }

    /** 透過 Session API 建立具說明的合成定義
     *
     * @param  string  $base  Session 專案路徑
     * @param  string  $name  合成標籤名稱
     * @return array 標籤寫入結果
     */
    private function createTag(string $base, string $name = 'Future'): array
    {
        return $this->postJson($base.'/tags', ['name' => $name, 'description' => 'Record only; do not claim automatically', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
    }

    /** 定義改名保持 ID, 明確移除關聯與保留舊定義, 重播及版本衝突安全
     * @return void
     */
    public function test_tag_definition_and_issue_membership_lifecycle_is_bounded_and_replay_safe(): void
    {
        [$owner, $project, $base] = $this->tagFixture();
        $create = ['name' => 'Future', 'color' => '#6653bd', 'description' => 'Do not execute', 'requestId' => (string) Str::uuid()];
        $tag = $this->postJson($base.'/tags', $create)->assertOk()->json('data');
        $this->postJson($base.'/tags', $create)->assertOk()->assertJsonPath('data.id', $tag['id']);
        $this->assertSame(1, ProjectTag::where('public_id', $tag['id'])->count());
        $this->postJson($base.'/tags', [...$create, 'name' => 'future', 'requestId' => (string) Str::uuid()])->assertStatus(400);
        $this->getJson($base.'/tags?perPage=1')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.description', 'Do not execute')->assertJsonMissingPath('data.items.0.name_key');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Tagged work', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $delta = ['expectedRevision' => 1, 'addTagIds' => [$tag['id']], 'requestId' => (string) Str::uuid()];
        $this->patchJson($base.'/issues/'.$issue['id'].'/tags', $delta)->assertOk()->assertJsonPath('data.revision', 2);
        $this->patchJson($base.'/issues/'.$issue['id'].'/tags', $delta)->assertOk()->assertJsonPath('data.revision', 2);
        $this->getJson($base.'/issues/'.$issue['id'])->assertJsonPath('data.tags.0.id', $tag['id'])->assertJsonPath('data.tags.0.name', 'Future');
        $this->getJson($base.'/issues?'.http_build_query(['tagIds' => [$tag['id']]]))->assertJsonPath('data.total', 1);
        $rename = ['name' => 'Later', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()];
        $this->patchJson($base.'/tags/'.$tag['id'], $rename)->assertOk()->assertJsonPath('data.revision', 2);
        $this->getJson($base.'/issues/'.$issue['id'])->assertJsonPath('data.tags.0.name', 'Later')->assertJsonPath('data.revision', 2);
        $this->patchJson($base.'/tags/'.$tag['id'], [...$rename, 'requestId' => (string) Str::uuid()])->assertStatus(409);
        $delete = ['expectedRevision' => 2, 'requestId' => (string) Str::uuid()];
        $this->deleteJson($base.'/tags/'.$tag['id'], $delete)->assertOk()->assertJsonPath('data.revision', 3);
        $this->deleteJson($base.'/tags/'.$tag['id'], $delete)->assertOk()->assertJsonPath('data.revision', 3);
        $this->getJson($base.'/tags/'.$tag['id'])->assertStatus(404);
        $this->assertNotNull(ProjectTag::where('public_id', $tag['id'])->sole()->deleted_at);
        $this->assertSame(0, DB::table('issue_tags')->where('tag_id', ProjectTag::where('public_id', $tag['id'])->sole()->id)->count());
        $this->getJson($base.'/issues/'.$issue['id'])->assertJsonPath('data.tags', [])->assertJsonPath('data.revision', 2);
        $history = $this->getJson($base.'/issues/'.$issue['id'].'/history')->assertOk()->json('data.items');
        $event = collect($history)->first(fn ($event) => ($event['changes'][0]['field'] ?? '') === 'tags');
        $this->assertSame($tag['id'], $event['changes'][0]['after'][0]['id']);
        $this->assertSame('Future', $event['changes'][0]['after'][0]['name']);
        $this->createTag($base, 'Later');
    }

    /** 公開定義 CRUD 與正式 Schema 相同, 寫入版本與即時看板版本都有變化
     * @return void
     */
    public function test_public_tag_definition_crud_matches_schema_and_board_version(): void
    {
        [$owner, $project, $base] = $this->tagFixture();
        $before = $this->getJson($base.'/boards/default/version')->assertOk()->json('data.version');
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'Tag manager', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $headers = ['Authorization' => 'Bearer '.$token];
        Auth::forgetGuards();
        $public = '/api/public/v1/projects/'.$project['id'];
        $tag = $this->postJson($public.'/tags', ['name' => 'Season 0', 'description' => 'Current delivery', 'requestId' => (string) Str::uuid()], $headers)->assertOk()->json('data');
        $this->patchJson($public.'/tags/'.$tag['id'], ['color' => '#008866', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $headers)->assertOk()->assertJsonPath('data.revision', 2);
        $response = $this->getJson($public.'/tags/'.$tag['id'], $headers)->assertOk()->assertJsonPath('data.color', '#008866');
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $schema = (object) ['$ref' => '#/components/schemas/ProjectTag', 'components' => json_decode(json_encode($spec['components']))];
        $this->assertTrue((new CompliantValidator)->validate(json_decode(json_encode($response->json('data'))), $schema)->isValid());
        $this->getJson($public.'/boards/default/version', $headers)->assertStatus(404); // No undocumented public version endpoint.
        $this->deleteJson($public.'/tags/'.$tag['id'], ['expectedRevision' => 2, 'requestId' => (string) Str::uuid()], $headers)->assertOk();
        Auth::forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $owner['user']['email'], 'password' => 'isolated-tag-password'])->assertOk();
        $this->assertNotSame($before, $this->getJson($base.'/boards/default/version')->assertOk()->json('data.version'));
    }

    /** read PAT 不能寫, write PAT 保留即時 ACL, 不跨租戶或跨專案套標
     * @return void
     */
    public function test_public_tag_permissions_scope_tenant_and_replay_revocation(): void
    {
        [$owner, $project, $base] = $this->tagFixture();
        $tag = $this->createTag($base);
        $user = User::create(['id' => (string) Str::uuid(), 'name' => 'Developer', 'email' => Str::uuid().'@example.test', 'password' => Hash::make('isolated-tag-password')]);
        $store = (new RecordStore)->forWorkspace($owner['workspace']['id']);
        $store->put('members', $user->id, ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'user']);
        $store->put('projectMembers', ProjectAccess::memberId($project['id'], $user->id), ['id' => ProjectAccess::memberId($project['id'], $user->id), 'projectId' => $project['id'], 'userId' => $user->id, 'roleId' => 'developer', 'revision' => 1]);
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Private tags', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'isolated-tag-password'])->assertOk();
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'writer', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $read = $this->postJson('/api/v1/personal-tokens', ['name' => 'reader', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        Auth::forgetGuards();
        $public = '/api/public/v1/projects/'.$project['id'];
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->getJson($public.'/tags', $headers)->assertOk()->assertJsonPath('data.items.0.description', 'Record only; do not claim automatically');
        $this->postJson($public.'/tags', ['name' => 'Cannot manage', 'requestId' => (string) Str::uuid()], $headers)->assertStatus(403);
        $input = ['expectedRevision' => 1, 'addTagIds' => [$tag['id']], 'requestId' => (string) Str::uuid()];
        $this->patchJson($public.'/issues/'.$issue['id'].'/tags', $input, ['Authorization' => 'Bearer '.$read])->assertStatus(403);
        $this->patchJson($public.'/issues/'.$issue['id'].'/tags', $input, $headers)->assertOk()->assertJsonPath('data.revision', 2);
        $audits = IntegrationAudit::count();
        $this->patchJson($public.'/issues/'.$issue['id'].'/tags', $input, $headers)->assertOk();
        $this->assertSame($audits, IntegrationAudit::count());
        $this->patchJson($public.'/issues/'.$issue['id'].'/tags', [...$input, 'addTagIds' => [], 'requestId' => (string) Str::uuid()], $headers)->assertStatus(409);
        $assignment = $store->require('projectMembers', ProjectAccess::memberId($project['id'], $user->id));
        $store->put('projectMembers', $assignment['id'], [...$assignment, 'removedAt' => now()->toISOString()]);
        $this->patchJson($public.'/issues/'.$issue['id'].'/tags', $input, $headers)->assertStatus(404);
        $this->getJson($public.'/tags/'.$tag['id'], $headers)->assertStatus(404);
        [$other, $otherProject, $otherBase] = $this->tagFixture();
        $this->getJson($otherBase.'/tags/'.$tag['id'])->assertStatus(404);
        $otherIssue = $this->postJson('/api/rpc/createIssue', ['projectId' => $otherProject['id'], 'title' => 'Other', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->patchJson($otherBase.'/issues/'.$otherIssue['id'].'/tags', ['expectedRevision' => 1, 'addTagIds' => [$tag['id']], 'requestId' => (string) Str::uuid()])->assertStatus(404);
    }

    /** 卡片上限、SQL 全選交集、輸入邊界與一頁一次預載, 不形成 N+1
     * @return void
     */
    public function test_tag_limits_literal_search_sql_intersection_and_constant_eager_loading(): void
    {
        [$owner, $project, $base] = $this->tagFixture();
        $ids = [];
        for ($i = 0; $i < 21; $i++) {
            $ids[] = $this->createTag($base, 'Tag_'.$i)['id'];
        }
        $this->getJson($base.'/tags?search=%25&perPage=1')->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson($base.'/tags', ['name' => 'Bad', 'color' => 'red', 'requestId' => (string) Str::uuid()])->assertStatus(400);
        $this->postJson($base.'/tags', ['name' => 'Bad', 'actor' => 'owner', 'requestId' => (string) Str::uuid()])->assertStatus(400);
        $issues = [];
        for ($i = 0; $i < 3; $i++) {
            $issues[] = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Tagged '.$i, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        }
        $this->patchJson($base.'/issues/'.$issues[0]['id'].'/tags', ['expectedRevision' => 1, 'addTagIds' => array_slice($ids, 0, 20), 'requestId' => (string) Str::uuid()])->assertOk();
        $this->patchJson($base.'/issues/'.$issues[0]['id'].'/tags', ['expectedRevision' => 2, 'addTagIds' => [$ids[20]], 'requestId' => (string) Str::uuid()])->assertStatus(400);
        $this->patchJson($base.'/issues/'.$issues[1]['id'].'/tags', ['expectedRevision' => 1, 'addTagIds' => [$ids[0]], 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson($base.'/issues?'.http_build_query(['tagIds' => [$ids[0], $ids[1]], 'perPage' => 1]))->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $issues[0]['id']);
        $counts = [];
        foreach ([1, 20] as $size) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->getJson($base.'/issues?perPage='.$size)->assertOk();
            $counts[] = count(array_filter(DB::getQueryLog(), fn ($query) => str_contains(strtolower($query['query']), 'join') && str_contains($query['query'], 'issue_tags')));
            DB::disableQueryLog();
        }
        $this->assertSame([1, 1], $counts);
    }
}
