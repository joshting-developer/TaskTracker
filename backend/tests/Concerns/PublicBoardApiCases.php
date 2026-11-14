<?php

namespace Tests\Concerns;

use App\Authorization\ProjectAccess;
use App\Models\IntegrationAudit;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;
use Opis\JsonSchema\CompliantValidator;

/** 公開看板查詢的摘要範圍、Epic展開與租戶隔離, 雙引擎共用 */
trait PublicBoardApiCases
{
    /**
     * 以純Bearer讀取有界摘要, 明確收錄與Epic展開分開, Default固定全收
     *
     * @return void
     */
    public function test_public_board_reads_are_scoped_bounded_and_expand_epics(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Board owner', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-public-board', 'workspaceName' => 'Board scope'])->assertOk();
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'board-read', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $headers = ['Authorization' => 'Bearer '.$token];
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Project', 'code' => 'BOARD', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $epic = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Epic', 'type' => 'epic', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $child = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Child', 'epicId' => $epic['id'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Unselected', 'requestId' => (string) Str::uuid()])->assertOk();
        $board = $this->postJson('/api/rpc/createBoard', ['projectId' => $project['id'], 'name' => 'Topic', 'description' => 'Only this topic', 'selectedIssueIds' => [$epic['id']], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $foreign = $this->postJson('/api/rpc/createProject', ['name' => 'Unrelated', 'code' => 'FOREIGN', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $foreignIssue = $this->postJson('/api/rpc/createIssue', ['projectId' => $foreign['id'], 'title' => 'Foreign secret', 'description' => str_repeat('unrelated body ', 300), 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $foreignBoard = $this->postJson('/api/rpc/createBoard', ['projectId' => $foreign['id'], 'name' => 'Other scope', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $base = '/api/public/v1/projects/'.$project['id'].'/boards';
        $this->getJson($base.'/'.$foreignBoard['id'], $headers)->assertStatus(404);

        $this->getJson($base.'?perPage=1', $headers)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.description', 'Only this topic')->assertJsonPath('data.items.0.issueCount', 2)->assertJsonPath('data.defaultBoard.issueCount', 3)->assertJsonMissingPath('data.items.0.selectedIssueIds')->assertJsonMissingPath('data.items.0.issues');
        $this->getJson($base.'/'.$board['id'], $headers)->assertOk()->assertJsonPath('data.selectedIssueIds', [$epic['id']]);
        $one = $this->getJson($base.'/'.$board['id'].'/issues?perPage=1', $headers)->assertOk()->assertJsonPath('data.total', 2)->assertJsonCount(1, 'data.items')->assertJsonMissingPath('data.items.0.description')->assertJsonMissingPath('data.items.0.history')->json('data.items.0.id');
        $two = $this->getJson($base.'/'.$board['id'].'/issues?perPage=1&page=2', $headers)->assertOk()->assertJsonCount(1, 'data.items')->json('data.items.0.id');
        $this->assertEqualsCanonicalizing([$epic['id'], $child['id']], [$one, $two]);
        $this->getJson($base.'/default', $headers)->assertOk()->assertJsonPath('data.isDefault', true)->assertJsonPath('data.selectedIssueIds', []);
        $this->getJson($base.'?perPage=101', $headers)->assertStatus(400);
        $this->postJson('/api/rpc/archiveBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'], 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson($base, $headers)->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson($base.'?archived=1', $headers)->assertOk()->assertJsonPath('data.items.0.id', $board['id']);
        $this->getJson($base.'/'.$board['id'], $headers)->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/register', ['name' => 'Other tenant', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-public-board', 'workspaceName' => 'Other scope'])->assertOk();
        $otherToken = $this->postJson('/api/v1/personal-tokens', ['name' => 'other', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $this->getJson($base, ['Authorization' => 'Bearer '.$otherToken])->assertStatus(404);
        $this->getJson($base.'/'.$board['id'], ['Authorization' => 'Bearer '.$otherToken])->assertStatus(404);
        $this->getJson($base)->assertStatus(401);
    }

    /**
     * 建立隔離看板、Project與兩個不同PAT, 不使用現用帳號
     *
     * @return array 測試Project、身分與憑證
     */
    private function boardFixture(): array
    {
        $actor = $this->postJson('/api/auth/register', ['name' => 'Board writer', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-public-board', 'workspaceName' => 'Writes'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Writes', 'code' => 'BWRITE', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $headers = [];
        foreach (['agent-a', 'agent-b'] as $name) {
            $token = $this->postJson('/api/v1/personal-tokens', ['name' => $name, 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
            $headers[] = ['Authorization' => 'Bearer '.$token];
        }

        return [$project, $actor, ...$headers];
    }

    /**
     * 部分更新不清空名稱或簡述, 兩個呼叫端衝突後重讀再套差量不會遺失彼此收錄
     *
     * @return void
     */
    public function test_public_board_delta_is_revision_checked_replay_safe_and_preserves_other_membership(): void
    {
        [$p, , $a, $b] = $this->boardFixture();
        $base = '/api/public/v1/projects/'.$p['id'];
        $createdInput = ['name' => 'Classify', 'description' => 'Keep this', 'requestId' => (string) Str::uuid()];
        $board = $this->postJson($base.'/boards', $createdInput, $a)->assertOk()->assertJsonMissingPath('data.selectedIssueIds')->json('data');
        $audit = IntegrationAudit::count();
        $this->postJson($base.'/boards', $createdInput, $a)->assertOk()->assertJsonPath('data.id', $board['id']);
        $this->assertSame($audit, IntegrationAudit::count());
        $one = $this->postJson($base.'/issues', ['title' => 'One', 'requestId' => (string) Str::uuid()], $a)->assertOk()->json('data');
        $two = $this->postJson($base.'/issues', ['title' => 'Two', 'requestId' => (string) Str::uuid()], $b)->assertOk()->json('data');
        $url = $base.'/boards/'.$board['id'];
        $delta = ['expectedRevision' => 1, 'addIssueIds' => [$one['id']], 'requestId' => (string) Str::uuid()];
        $this->patchJson($url, $delta, $a)->assertOk()->assertJsonPath('data.revision', 2);
        $this->patchJson($url, $delta, $a)->assertOk()->assertJsonPath('data.revision', 2);
        $this->patchJson($url, [...$delta, 'addIssueIds' => [$two['id']]], $a)->assertStatus(409);
        $this->patchJson($url, ['expectedRevision' => 1, 'addIssueIds' => [$two['id']], 'requestId' => (string) Str::uuid()], $b)->assertStatus(409);
        $fresh = $this->getJson($url, $b)->assertOk()->assertJsonPath('data.description', 'Keep this')->json('data');
        $this->patchJson($url, ['expectedRevision' => $fresh['revision'], 'addIssueIds' => [$two['id']], 'requestId' => (string) Str::uuid()], $b)->assertOk()->assertJsonPath('data.revision', 3);
        $selected = $this->getJson($url, $a)->assertOk()->json('data.selectedIssueIds');
        $this->assertEqualsCanonicalizing([$one['id'], $two['id']], $selected);
        $this->patchJson($url, ['expectedRevision' => 3, 'removeIssueIds' => [$one['id']], 'requestId' => (string) Str::uuid()], $a)->assertOk();
        $this->getJson($url, $a)->assertOk()->assertJsonPath('data.selectedIssueIds', [$two['id']])->assertJsonPath('data.name', 'Classify');
        foreach ([['selectedIssueIds' => []], ['archivedAt' => now()->toISOString()], ['name' => ''], ['addIssueIds' => [$two['id']], 'removeIssueIds' => [$two['id']]], ['addIssueIds' => ['missing']], ['addIssueIds' => array_fill(0, 201, $one['id'])]] as $bad) {
            $this->patchJson($url, ['expectedRevision' => 4, 'requestId' => (string) Str::uuid(), ...$bad], $a)->assertStatus(400);
        }
        $this->patchJson($base.'/boards/default', ['expectedRevision' => 1, 'name' => 'No', 'requestId' => (string) Str::uuid()], $a)->assertStatus(400);
        $this->postJson('/api/rpc/archiveBoard', ['id' => $board['id'], 'expectedRevision' => 4, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->patchJson($url, ['expectedRevision' => 5, 'name' => 'No', 'requestId' => (string) Str::uuid()], $a)->assertStatus(404);
    }

    /**
     * 一張卡同交易收錄多板, 任一板不合法時編號、卡片、收錄和來源紀錄一起回滾
     *
     * @return void
     */
    public function test_public_issue_board_ids_are_atomic_and_replay_cannot_bypass_board_permission(): void
    {
        [$p, $actor, $a] = $this->boardFixture();
        $base = '/api/public/v1/projects/'.$p['id'];
        $first = $this->postJson($base.'/boards', ['name' => 'A', 'requestId' => (string) Str::uuid()], $a)->assertOk()->json('data');
        $second = $this->postJson($base.'/boards', ['name' => 'B', 'requestId' => (string) Str::uuid()], $a)->assertOk()->json('data');
        $archived = $this->postJson($base.'/boards', ['name' => 'Archived', 'requestId' => (string) Str::uuid()], $a)->assertOk()->json('data');
        $this->postJson('/api/rpc/archiveBoard', ['id' => $archived['id'], 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertOk();
        $foreign = $this->postJson('/api/rpc/createProject', ['name' => 'Elsewhere', 'code' => 'ELSE', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $foreignBoard = $this->postJson('/api/rpc/createBoard', ['projectId' => $foreign['id'], 'name' => 'Wrong project', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $before = Project::where('public_id', $p['id'])->firstOrFail()->next_number;
        $count = Issue::count();
        $audit = IntegrationAudit::count();
        foreach ([[$first['id'], 'missing'], [$first['id'], $archived['id']], [$first['id'], $foreignBoard['id']], ['default'], array_fill(0, 21, $first['id'])] as $bad) {
            $response = $this->postJson($base.'/issues', ['title' => 'Rollback', 'boardIds' => $bad, 'requestId' => (string) Str::uuid()], $a);
            $this->assertContains($response->status(), [400, 404]);
            $this->assertSame($count, Issue::count());
            $this->assertSame($audit, IntegrationAudit::count());
            $this->assertSame($before, Project::where('public_id', $p['id'])->firstOrFail()->next_number);
            $this->getJson($base.'/boards/'.$first['id'], $a)->assertOk()->assertJsonPath('data.selectedIssueIds', []);
        }
        $input = ['title' => 'Classified', 'boardIds' => [$first['id'], $second['id']], 'requestId' => (string) Str::uuid()];
        $issue = $this->postJson($base.'/issues', $input, $a)->assertOk()->json('data');
        $this->postJson($base.'/issues', $input, $a)->assertOk()->assertJsonPath('data.id', $issue['id']);
        foreach ([$first, $second] as $board) {
            $this->getJson($base.'/boards/'.$board['id'], $a)->assertOk()->assertJsonPath('data.selectedIssueIds', [$issue['id']])->assertJsonPath('data.revision', 2);
        }
        $this->patchJson($base.'/issues/'.$issue['id'], ['boardIds' => [$first['id']], 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $a)->assertStatus(400);
        $member = WorkspaceMember::where('user_id', $actor['user']['id'])->firstOrFail();
        $member->update(['role' => 'user']);
        $model = Project::where('public_id', $p['id'])->firstOrFail();
        ProjectMember::create(['public_id' => ProjectAccess::memberId($p['id'], $actor['user']['id']), 'workspace_id' => $member->workspace_id, 'project_id' => $model->id, 'member_id' => $member->id, 'role_id' => 'developer']);
        // developer仍可建卡, 但不能靠舊receipt繞過已失去的board.manage
        $this->postJson($base.'/issues', $input, $a)->assertStatus(403);
        $this->postJson($base.'/boards', ['name' => 'Denied', 'requestId' => (string) Str::uuid()], $a)->assertStatus(403);
        $this->postJson($base.'/issues', ['title' => 'Ordinary', 'requestId' => (string) Str::uuid()], $a)->assertOk();
    }

    /**
     * 真實HTTP回應逐一對照正式OpenAPI, 包含錯誤與列表／詳細投影
     *
     * @return void
     */
    public function test_public_board_operations_match_the_formal_contract_and_read_scope_cannot_write(): void
    {
        [$p, , $a] = $this->boardFixture();
        $prefix = '/api/public/v1/projects/'.$p['id'].'/boards';
        $template = '/api/public/v1/projects/{project}/boards';
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $check = function ($response, string $path, string $method) use ($spec) {
            $schema = $spec['paths'][$path][$method]['responses'][(string) $response->status()]['content']['application/json']['schema'];
            $root = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$schema, 'components' => $spec['components']]));
            $this->assertTrue((new CompliantValidator)->validate(json_decode($response->getContent()), $root)->isValid(), '公開看板回應不符合正式Schema');
            $response->assertCookieMissing(config('session.cookie'));
        };
        $created = $this->postJson($prefix, ['name' => 'Contract', 'requestId' => (string) Str::uuid()], $a)->assertOk();
        $check($created, $template, 'post');
        $id = $created->json('data.id');
        $check($this->getJson($prefix, $a)->assertOk(), $template, 'get');
        $check($this->getJson($prefix.'/'.$id, $a)->assertOk(), $template.'/{board}', 'get');
        $check($this->getJson($prefix.'/'.$id.'/issues', $a)->assertOk(), $template.'/{board}/issues', 'get');
        $check($this->patchJson($prefix.'/'.$id, ['name' => 'Rename', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $a)->assertOk(), $template.'/{board}', 'patch');
        $check($this->patchJson($prefix.'/'.$id, ['name' => 'Stale', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $a)->assertStatus(409), $template.'/{board}', 'patch');
        $read = $this->postJson('/api/v1/personal-tokens', ['name' => 'read-only', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $check($this->postJson($prefix, ['name' => 'Denied', 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer '.$read])->assertStatus(403), $template, 'post');
    }
}
