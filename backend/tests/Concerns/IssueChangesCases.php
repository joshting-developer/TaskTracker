<?php

namespace Tests\Concerns;

use App\Authorization\ProjectAccess;
use App\Database\DatabaseTimes;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Opis\JsonSchema\CompliantValidator;

/** 兩引擎真HTTP驗證同秒cursor、有界資料投影、範圍綁定與撤權。 */
trait IssueChangesCases
{
    use PersonalTokenCases;

    /**
     * 人造身分、Project、Issue模板、PAT與同秒時間。
     *
     * @return array 人造身分、Project、Issue模板、PAT與同秒時間。
     */
    private function changesFixture(): array
    {
        $identity = $this->tokenRegister();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'changes', 'code' => 'CHG', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $created = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'source', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $model = Issue::where('public_id', $created['id'])->sole();
        $time = now('UTC')->subMinute()->startOfSecond()->toISOString();
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->sole();
        $model->update(['priority' => 'high', 'assignee_id' => $member->id, 'updated_at' => DatabaseTimes::store($time)]);
        for ($n = 2; $n <= 6; $n++) {
            $row = $model->replicate();
            $row->public_id = (string) Str::uuid();
            $row->identifier = 'CHG-'.$n;
            $row->type = $n === 6 ? 'bug' : 'task';
            $row->created_at = $model->created_at;
            $row->updated_at = DatabaseTimes::store($time);
            $row->save();
        }

        $other = $this->postJson('/api/rpc/createProject', ['name' => 'unrelated', 'code' => 'OTH', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $otherIssue = $this->postJson('/api/rpc/createIssue', ['projectId' => $other['id'], 'title' => 'unrelated', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $attributes = Issue::where('public_id', $otherIssue['id'])->sole()->getAttributes();
        unset($attributes['id']);
        $batch = [];
        for ($n = 2; $n <= 1001; $n++) {
            $batch[] = [...$attributes, 'public_id' => (string) Str::uuid(), 'identifier' => 'OTH-'.$n];
        }
        foreach (array_chunk($batch, 100) as $chunk) {
            DB::table('issues')->insert($chunk);
        }

        return [$identity, $project, $model, $this->tokenIssue(), $time, $other];
    }

    /**
     * 以純Bearer讀取增量端點並驗證正式回應Schema。
     *
     * @param  string  $token  只在測試記憶體的PAT
     * @param  string  $project  Project公開ID
     * @param  array  $query  HTTP查詢
     * @param  int  $status  預期HTTP狀態
     * @return array 正式Schema驗證後的JSON
     */
    private function changeRead(string $token, string $project, array $query = [], int $status = 200): array
    {
        Auth::forgetGuards();
        $response = $this->getJson('/api/public/v1/projects/'.$project.'/issue-changes?'.http_build_query($query), ['Authorization' => 'Bearer '.$token])->assertStatus($status)->assertCookieMissing(config('session.cookie'));
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $media = $spec['paths']['/api/public/v1/projects/{project}/issue-changes']['get']['responses'][(string) $status]['content']['application/json'];
        $schema = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', ...$media['schema'], 'components' => $spec['components']]));
        $this->assertTrue((new CompliantValidator)->validate(json_decode($response->getContent()), $schema)->isValid(), '增量回應不符合正式Schema');

        return $response->json();
    }

    /**
     * 同秒唯一鍵、有界SQL、不讀活動、包含邊界水位及null歷史。
     *
     * @return void 同秒唯一鍵、有界SQL、不讀活動、包含邊界水位及null歷史。
     */
    public function test_changes_cursor_is_stable_for_same_timestamp_and_sql_is_bounded(): void
    {
        [$identity,$project,$model,$token,$time,$other] = $this->changesFixture();
        $base = ['perPage' => 2, 'updatedSince' => $time, 'type' => 'task', 'priority' => 'high', 'assignee' => $identity['user']['id'], 'state' => ['open']];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->changeRead($token['plainTextToken'], $project['id'], $base)['data'];
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $selects = array_filter($log, fn ($row) => preg_match('/from [`"]?issues[`"]? where/i', $row['query']));
        $this->assertNotEmpty($selects);
        if (DB::getDriverName() === 'mysql') {
            $plan = DB::select('EXPLAIN SELECT id, updated_at FROM issues WHERE workspace_id=? AND project_id=? AND updated_at>=? ORDER BY updated_at,id LIMIT 3', [$model->workspace_id, $model->project_id, DatabaseTimes::store($time)]);
            $this->assertSame('issues_change_cursor', $plan[0]->key);
            $this->assertStringNotContainsString('filesort', $plan[0]->Extra);
        }
        foreach ($selects as $select) {
            $this->assertMatchesRegularExpression('/limit 3/i', $select['query']);
            $this->assertDoesNotMatchRegularExpression('/count\(|offset|description|legacy_attributes|time_entries|comments/i', $select['query']);
        }
        $this->assertCount(2, $first['items']);
        $this->assertArrayNotHasKey('description', $first['items'][0]);
        $this->assertArrayNotHasKey('total', $first);
        $ids = array_column($first['items'], 'id');
        $next = $first['nextCursor'];
        while ($next !== null) {
            $page = $this->changeRead($token['plainTextToken'], $project['id'], [...$base, 'cursor' => $next])['data'];
            $this->assertSame($first['syncUntil'], $page['syncUntil']);
            $ids = [...$ids, ...array_column($page['items'], 'id')];
            $next = $page['nextCursor'];
        }
        $this->assertCount(5, $ids);
        $this->assertCount(5, array_unique($ids));
        $boundary = $model->replicate();
        $boundary->public_id = (string) Str::uuid();
        $boundary->identifier = 'CHG-7';
        $boundary->updated_at = DatabaseTimes::store($first['syncUntil']);
        $boundary->save();
        $this->assertSame($boundary->public_id, $this->changeRead($token['plainTextToken'], $project['id'], ['updatedSince' => $first['syncUntil']])['data']['items'][0]['id']);
        $model->update(['updated_at' => null]);
        $all = $this->changeRead($token['plainTextToken'], $project['id'], ['perPage' => 1])['data'];
        $this->assertNull($all['items'][0]['updatedAt']);
        $this->assertNotNull($all['nextCursor']);
        $second = $this->changeRead($token['plainTextToken'], $project['id'], ['perPage' => 1, 'cursor' => $all['nextCursor']])['data'];
        $this->assertNotSame($all['items'][0]['id'], $second['items'][0]['id']);
    }

    /**
     * Cursor不能改範圍／條件或繞過撤權；日期／大小／分頁有界。
     *
     * @return void Cursor不能改範圍／條件或繞過撤權；日期／大小／分頁有界。
     */
    public function test_cursor_binds_filters_and_project_and_rechecks_live_access(): void
    {
        [$identity,$project,$model,$token,$time,$other] = $this->changesFixture();
        $base = ['perPage' => 1];
        $cursor = $this->changeRead($token['plainTextToken'], $project['id'], $base)['data']['nextCursor'];
        foreach ([['perPage' => 101], ['page' => 1], ['sort' => 'priority'], ['updatedSince' => 'yesterday'], ['cursor' => 'tampered'], ['cursor' => $cursor, 'perPage' => 2], ['cursor' => $cursor, 'perPage' => 1, 'type' => 'bug']] as $bad) {
            $this->changeRead($token['plainTextToken'], $project['id'], $bad, 400);
        }
        $this->changeRead($token['plainTextToken'], $other['id'], [...$base, 'cursor' => $cursor], 400);
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->sole();
        $p = Project::where('public_id', $project['id'])->sole();
        $member->update(['role' => 'user']);
        ProjectMember::updateOrCreate(['member_id' => $member->id, 'project_id' => $p->id], ['public_id' => ProjectAccess::memberId($project['id'], $identity['user']['id']), 'workspace_id' => $member->workspace_id, 'role_id' => 'maintainer']);
        $this->changeRead($token['plainTextToken'], $project['id'], [...$base, 'cursor' => $cursor]);
        ProjectMember::where('member_id', $member->id)->where('project_id', $p->id)->update(['removed_at' => now()]);
        $this->changeRead($token['plainTextToken'], $project['id'], [...$base, 'cursor' => $cursor], 404);
        WorkspaceMember::whereKey($member->id)->update(['disabled_at' => now()]);
        $this->changeRead($token['plainTextToken'], $project['id'], [...$base, 'cursor' => $cursor], 401);
    }
}
