<?php

namespace Tests\Concerns;

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 卡號與字面contains搜尋在Session／PAT使用相同租戶限定 */
trait IssueSearchCases
{
    /** 驗完整卡號只命中本人專案等值, prefix明確指定, 一般字面不丟失
     * @return void
     */
    public function test_issue_search_exact_prefix_and_literal_fallback_are_scoped(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Searcher', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-search-password', 'workspaceName' => 'Search'])->assertOk();
        $rpc = fn ($op, $input) => $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input])->assertOk()->json('data');
        $project = $rpc('createProject', ['name' => 'Search', 'code' => 'SRC']);
        $one = $rpc('createIssue', ['projectId' => $project['id'], 'title' => '會員 API 100%_! punctuation', 'description' => '短字中, MixedCase']);
        $two = $rpc('createIssue', ['projectId' => $project['id'], 'title' => '文件提到 SRC-1', 'description' => 'literal marker']);
        $model = Project::where('public_id', $project['id'])->sole();
        $source = (array) DB::table('issues')->where('public_id', $one['id'])->first();
        unset($source['id']);
        DB::table('issues')->insert([...$source, 'public_id' => (string) Str::uuid(), 'identifier' => 'SRC-10', 'title' => 'Prefix result', 'description' => '', 'storage_position' => 10]);
        $root = '/api/v1/projects/'.$project['id'].'/issues?search=';
        $query = fn ($term) => $this->getJson($root.rawurlencode($term))->assertOk()->json('data');
        $this->assertSame([$one['id']], array_column($query('src-1')['items'], 'id'));
        $this->assertSame(2, $query('src-1*')['total']);
        $this->assertSame(3, $query('SRC-*')['total']);
        $this->assertSame(3, $query('text:SRC-1')['total'], 'Explicit contains can find titles mentioning a card number');
        foreach (['會員', '中', 'mixedcase', 'API', '100%_!', '%_', '!'] as $term) {
            $this->assertSame([$one['id']], array_column($query($term)['items'], 'id'), $term);
        }
        $this->assertSame(0, $query('SRC-99')['total']);
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'search', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $this->getJson('/api/public/v1/projects/'.$project['id'].'/issues?search=src-1', ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $one['id']);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $query('src-1');
            $sql = collect(DB::getQueryLog())->filter(fn ($row) => str_contains($row['query'], 'issues'))->pluck('query')->implode("\n");
            $this->assertStringContainsString('identifier', $sql);
            $this->assertStringNotContainsString('LOWER(description)', $sql);
            $this->assertStringNotContainsString('LOWER(title)', $sql);
        } finally {
            DB::disableQueryLog();
        }
        $other = $rpc('createProject', ['name' => 'Other', 'code' => 'OTH']);
        $rpc('createIssue', ['projectId' => $other['id'], 'title' => 'SRC-1']);
        $this->assertSame(1, $query('src-1')['total']);
        $this->assertSame(0, $this->getJson('/api/v1/projects/'.$other['id'].'/issues?search=src-1')->assertOk()->json('data.total'));
        $this->getJson('/api/v1/projects/'.$project['id'].'/issue-options?search=src-1')->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame($model->id, Issue::where('public_id', $two['id'])->value('project_id'));
    }
}
