<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\CompliantValidator;

/** 雙引擎驗證儲存條件、手動例外與同一 SQL 的統計及預覽 */
trait BoardRuleCases
{
    /** 真實 Session 寫入補上獨立 requestId
     * @param  string  $operation  固定 RPC
     * @param  array  $input  合成測試資料
     * @return TestResponse 真實回應
     */
    private function ruleRpc(string $operation, array $input): TestResponse
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /** 建立獨立租戶、Project、Tag 與 Session 路徑
     * @return array{array,array,string,array} 可信身分、Project、路徑與標籤
     */
    private function ruleFixture(): array
    {
        Auth::forgetGuards();
        $identity = $this->postJson('/api/auth/register', ['name' => 'Board rules', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-board-rules', 'workspaceName' => 'Rules'])->assertOk()->json('data');
        $project = $this->ruleRpc('createProject', ['name' => 'Rules', 'code' => 'RULE'])->assertOk()->json('data');
        $base = '/api/v1/projects/'.$project['id'];
        $tag = $this->postJson($base.'/tags', ['name' => 'Season 1', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$identity, $project, $base, $tag];
    }

    /** 卡片貼標籤走實際差量入口
     * @param  string  $base  同 Project 路徑
     * @param  array  $issue  卡片識別與版本
     * @param  array  $tags  合成標籤 ID
     * @return void
     */
    private function ruleTag(string $base, array $issue, array $tags): void
    {
        $this->patchJson($base.'/issues/'.$issue['id'].'/tags', ['addTagIds' => $tags, 'expectedRevision' => $issue['revision'], 'requestId' => (string) Str::uuid()])->assertOk();
    }

    /** 新卡貼標、狀態及類型交集、手動 Epic 子卡與排除去重, 統計與分頁一致
     * @return void
     */
    public function test_board_rules_and_manual_exceptions_are_dynamic_and_counted_in_sql(): void
    {
        [, $project, $base, $tag] = $this->ruleFixture();
        $epic = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Selected epic', 'type' => 'epic'])->assertOk()->json('data');
        $child = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Dynamic child', 'epicId' => $epic['id'], 'state' => 'closed'])->assertOk()->json('data');
        $matched = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Matching task'])->assertOk()->json('data');
        $this->ruleTag($base, $matched, [$tag['id']]);
        $input = ['projectId' => $project['id'], 'name' => 'Automatic', 'autoInclude' => ['tagIds' => [$tag['id']], 'types' => ['task', 'bug'], 'states' => ['open']], 'selectedIssueIds' => [$epic['id'], $matched['id']], 'excludedIssueIds' => [$child['id']]];
        $board = $this->ruleRpc('createBoard', $input)->assertOk()->json('data');
        $path = $base.'/boards/'.$board['id'];
        $this->getJson($path)->assertOk()->assertJsonPath('data.issueCount', 2)->assertJsonPath('data.closedCount', 0)->assertJsonPath('data.autoInclude.tagIds.0', $tag['id'])->assertJsonPath('data.autoIncludeTags.0.name', 'Season 1')->assertJsonPath('data.excludedIssueIds.0', $child['id']);
        $this->getJson($path.'/issues?perPage=1')->assertOk()->assertJsonPath('data.total', 2)->assertJsonCount(1, 'data.items');
        $second = $this->getJson($path.'/issues?perPage=1&page=2')->assertOk()->json('data.items.0.id');
        $first = $this->getJson($path.'/issues?perPage=1')->assertOk()->json('data.items.0.id');
        $this->assertNotSame($first, $second);
        $new = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'New bug', 'type' => 'bug'])->assertOk()->json('data');
        $this->ruleTag($base, $new, [$tag['id']]);
        $this->getJson($path)->assertJsonPath('data.issueCount', 3);
        $this->ruleRpc('updateIssue', ['id' => $new['id'], 'state' => 'closed', 'expectedRevision' => 2])->assertOk();
        $this->getJson($path)->assertJsonPath('data.issueCount', 2);
        $input['excludedIssueIds'] = [];
        $input['id'] = $board['id'];
        $input['expectedRevision'] = 1;
        $this->ruleRpc('updateBoard', $input)->assertOk();
        $this->getJson($path)->assertJsonPath('data.issueCount', 3)->assertJsonPath('data.closedCount', 1);
        $listed = $this->getJson($base.'/boards')->assertOk()->json('data.items');
        $row = collect($listed)->firstWhere('id', $board['id']);
        $this->assertSame(3, $row['issueCount']);
        $this->assertSame(1, $row['closedCount']);
        // 顯式手動選取保留原能力, 不會因條件未命中而消失。
        $this->patchJson($base.'/issues/'.$matched['id'].'/tags', ['removeTagIds' => [$tag['id']], 'expectedRevision' => 2, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson($path)->assertJsonPath('data.issueCount', 3);
    }

    /** 預覽與正式結果相同, 自動命中的 Epic 不會無條件帶入子卡
     * @return void
     */
    public function test_board_preview_matches_saved_selection_and_automatic_epic_is_not_expanded(): void
    {
        [, $project, $base, $tag] = $this->ruleFixture();
        $epic = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Automatic epic', 'type' => 'epic'])->assertOk()->json('data');
        $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Unmatched child', 'epicId' => $epic['id']])->assertOk();
        $this->ruleTag($base, $epic, [$tag['id']]);
        $input = ['autoInclude' => ['tagIds' => [$tag['id']], 'types' => ['epic']], 'selectedIssueIds' => [], 'excludedIssueIds' => []];
        $before = DB::table('boards')->count();
        $preview = $this->postJson($base.'/board-preview', $input)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $epic['id'])->json('data');
        $this->assertSame($before, DB::table('boards')->count());
        $board = $this->ruleRpc('createBoard', [...$input, 'name' => 'Saved', 'projectId' => $project['id']])->assertOk()->json('data');
        $this->getJson($base.'/boards/'.$board['id'])->assertJsonPath('data.issueCount', $preview['total']);
        $this->getJson($base.'/boards/'.$board['id'].'/issues')->assertJsonCount(1, 'data.items');
        // 刪除 Tag 定義後規則保留, 不會變成未限制 Tag 的全部卡片。
        $this->deleteJson($base.'/tags/'.$tag['id'], ['expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertOk();
        $this->getJson($base.'/boards/'.$board['id'])->assertJsonPath('data.issueCount', 0)->assertJsonPath('data.autoInclude.tagIds.0', $tag['id']);
    }

    /** 無效參照整筆回滾, 舊手動看板保持原設定, 同 requestId 不重複規則
     * @return void
     */
    public function test_board_rule_updates_are_atomic_replay_safe_and_do_not_change_legacy_boards(): void
    {
        [, $project, $base, $tag] = $this->ruleFixture();
        $issue = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Manual'])->assertOk()->json('data');
        $board = $this->ruleRpc('createBoard', ['projectId' => $project['id'], 'name' => 'Manual', 'selectedIssueIds' => [$issue['id']]])->assertOk()->json('data');
        $path = $base.'/boards/'.$board['id'];
        $this->getJson($path)->assertJsonPath('data.autoInclude', null)->assertJsonPath('data.excludedIssueIds', [])->assertJsonPath('data.issueCount', 1);
        $bad = ['id' => $board['id'], 'name' => 'Must rollback', 'expectedRevision' => 1, 'selectedIssueIds' => [], 'autoInclude' => ['tagIds' => [(string) Str::uuid()]]];
        $this->ruleRpc('updateBoard', $bad)->assertStatus(400);
        $this->getJson($path)->assertJsonPath('data.revision', 1)->assertJsonPath('data.name', 'Manual')->assertJsonPath('data.selectedIssueIds.0', $issue['id']);
        $input = [...$bad, 'name' => 'Automatic', 'autoInclude' => ['tagIds' => [$tag['id']]], 'requestId' => (string) Str::uuid()];
        $this->ruleRpc('updateBoard', $input)->assertOk()->assertJsonPath('data.revision', 2);
        $this->ruleRpc('updateBoard', $input)->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertSame(2, DB::table('board_rules')->whereIn('board_id', DB::table('boards')->where('public_id', $board['id'])->select('id'))->count());
        $this->ruleRpc('updateBoard', [...$input, 'requestId' => (string) Str::uuid()])->assertStatus(409);
        $this->postJson($base.'/board-preview', ['autoInclude' => ['types' => ['unsupported']]])->assertStatus(400);
        $this->postJson($base.'/board-preview', ['workspaceId' => 'forged'])->assertStatus(400);
        $this->postJson($base.'/board-preview', ['autoInclude' => []])->assertStatus(400);
        $this->getJson($base.'/boards/default')->assertJsonPath('data.autoInclude', null)->assertJsonPath('data.issueCount', 1);
    }

    /** 純 Bearer 預覽及差量排除沿用目前資格與公開 Schema
     * @return void
     */
    public function test_public_board_rules_and_exclusion_deltas_match_schema_and_scope(): void
    {
        [, $project, $base, $tag] = $this->ruleFixture();
        $issue = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Public selection'])->assertOk()->json('data');
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'Rules', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $headers = ['Authorization' => 'Bearer '.$token];
        Auth::forgetGuards();
        $public = '/api/public/v1/projects/'.$project['id'].'/boards';
        $rules = ['types' => ['task']];
        $board = $this->postJson($public, ['name' => 'Public', 'autoInclude' => $rules, 'requestId' => (string) Str::uuid()], $headers)->assertOk()->json('data');
        $preview = $this->postJson($public.'/preview', ['autoInclude' => $rules], $headers)->assertOk()->assertJsonPath('data.total', 1);
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $schema = $spec['paths']['/api/public/v1/projects/{project}/boards/preview']['post']['responses']['200']['content']['application/json']['schema'];
        $this->assertTrue((new CompliantValidator)->validate(json_decode($preview->getContent()), json_decode(json_encode([...$schema, 'components' => $spec['components']])))->isValid());
        $this->patchJson($public.'/'.$board['id'], ['expectedRevision' => 1, 'excludeIssueIds' => [$issue['id']], 'requestId' => (string) Str::uuid()], $headers)->assertOk();
        $this->getJson($public.'/'.$board['id'], $headers)->assertJsonPath('data.issueCount', 0);
        $this->patchJson($public.'/'.$board['id'], ['expectedRevision' => 2, 'name' => 'Keep rules', 'requestId' => (string) Str::uuid()], $headers)->assertOk();
        $this->getJson($public.'/'.$board['id'], $headers)->assertJsonPath('data.autoInclude.types.0', 'task')->assertJsonPath('data.excludedIssueIds.0', $issue['id']);
        $this->patchJson($public.'/'.$board['id'], ['expectedRevision' => 3, 'unexcludeIssueIds' => [$issue['id']], 'requestId' => (string) Str::uuid()], $headers)->assertOk();
        $this->getJson($public.'/'.$board['id'], $headers)->assertJsonPath('data.issueCount', 1);
        $this->postJson($public.'/preview', ['autoInclude' => ['tagIds' => [(string) Str::uuid()]]], $headers)->assertStatus(400);
        $this->postJson($public.'/preview', ['autoInclude' => ['unknown' => true]], $headers)->assertStatus(400);
        $this->postJson($public.'/preview', ['autoInclude' => []], $headers)->assertStatus(400);
        $this->postJson('/api/public/v1/projects/'.Str::uuid().'/boards/preview', ['autoInclude' => $rules], $headers)->assertStatus(404);
    }

    /** 多 Tag 全符合、群組內 OR、首 20 筆預覽及實際跨 Project ID 拒絕
     * @return void
     */
    public function test_multi_tag_rules_preview_bounds_and_foreign_project_ids(): void
    {
        [, $project, $base, $tag] = $this->ruleFixture();
        $secondTag = $this->postJson($base.'/tags', ['name' => 'Security', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issues = [];
        for ($index = 0; $index < 25; $index++) {
            $issue = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Work '.$index, 'type' => $index === 0 ? 'bug' : 'task', 'state' => $index === 0 ? 'closed' : 'open'])->assertOk()->json('data');
            $issues[] = $issue;
            if ($index < 3) {
                $this->ruleTag($base, $issue, $index < 2 ? [$tag['id'], $secondTag['id']] : [$tag['id']]);
            }
        }
        $this->postJson($base.'/board-preview', ['autoInclude' => (object) []])->assertOk()->assertJsonPath('data.total', 25)->assertJsonCount(20, 'data.items');
        $rules = ['tagIds' => [$tag['id'], $secondTag['id']], 'types' => ['task', 'bug'], 'states' => ['open', 'closed']];
        $this->postJson($base.'/board-preview', ['autoInclude' => $rules])->assertOk()->assertJsonPath('data.total', 2);
        $board = $this->ruleRpc('createBoard', ['projectId' => $project['id'], 'name' => 'AND', 'autoInclude' => $rules])->assertOk()->json('data');
        $this->getJson($base.'/boards/'.$board['id'])->assertJsonPath('data.issueCount', 2)->assertJsonPath('data.closedCount', 1);
        $other = $this->ruleRpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk()->json('data');
        $foreign = $this->ruleRpc('createIssue', ['projectId' => $other['id'], 'title' => 'Foreign'])->assertOk()->json('data');
        $foreignTag = $this->postJson('/api/v1/projects/'.$other['id'].'/tags', ['name' => 'Foreign', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        foreach ([['selectedIssueIds' => [$foreign['id']]], ['excludedIssueIds' => [$foreign['id']]], ['autoInclude' => ['tagIds' => [$foreignTag['id']]]], ['autoInclude' => ['states' => ['unknown']]]] as $bad) {
            $this->postJson($base.'/board-preview', $bad)->assertStatus(400);
        }
    }

    /** 只有即時 board.manage 能預覽規則, 讀者仍可讀取已儲存看板
     * @return void
     */
    public function test_rule_preview_requires_current_board_management_permission(): void
    {
        [$owner, $project, $base] = $this->ruleFixture();
        $email = Str::uuid().'@example.test';
        $invite = $this->ruleRpc('inviteMember', ['email' => $email])->assertOk()->json('data');
        Auth::logout();
        Auth::forgetGuards();
        $reader = $this->postJson('/api/auth/register', ['name' => 'Reader', 'email' => $email, 'password' => 'isolated-board-rules', 'inviteToken' => $invite['token']])->assertOk()->json('data');
        Auth::logout();
        Auth::forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $owner['user']['email'], 'password' => 'isolated-board-rules'])->assertOk();
        $acl = $this->ruleRpc('getProjectMembers', ['projectId' => $project['id']])->assertOk()->json('data');
        $this->ruleRpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader['user']['id'], 'roleId' => 'reader', 'expectedRevision' => $acl['revision']])->assertOk();
        Auth::logout();
        Auth::forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'isolated-board-rules'])->assertOk();
        $this->postJson($base.'/board-preview', ['autoInclude' => (object) []])->assertStatus(403);
        $this->getJson($base.'/boards/default')->assertOk();
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'Read', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        Auth::forgetGuards();
        $this->postJson('/api/public/v1/projects/'.$project['id'].'/boards/preview', ['autoInclude' => (object) []], ['Authorization' => 'Bearer '.$token])->assertStatus(403);
    }
}
