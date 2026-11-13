<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 真實HTTP驗深分頁拒絕發生在Issue查詢之前, Session與PAT契約一致 */
trait IssuePaginationCases
{
    /**
     * 過界OFFSET不執行Issue COUNT／data, 每頁不同大小都依OFFSET計算
     *
     * @return void
     */
    public function test_issue_offset_budget_and_honest_page_metadata(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Pager', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-pagination-test', 'workspaceName' => 'Pages'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['requestId' => (string) Str::uuid(), 'name' => 'Pages', 'code' => 'PG'])->assertOk()->json('data.id');
        $this->postJson('/api/rpc/createIssue', ['requestId' => (string) Str::uuid(), 'projectId' => $project, 'title' => 'Page'])->assertOk();
        $root = '/api/v1/projects/'.$project.'/issues';
        $this->getJson($root.'?page=501&perPage=20')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.lastPage', 1)->assertJsonPath('data.accessibleLastPage', 1)->assertJsonPath('data.maxOffset', 10000)->assertJsonCount(0, 'data.items');
        $this->getJson($root.'?page=101&perPage=100')->assertOk();
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $this->getJson($root.'?page=502&perPage=20')->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
            $issueQueries = collect(DB::getQueryLog())->filter(fn ($row) => preg_match('/from ["`]?issues["`]? /i', $row['query']));
            $this->assertCount(0, $issueQueries, 'Reject before scanning Issue rows');
        } finally {
            DB::disableQueryLog();
        }
        $this->getJson($root.'?page=102&perPage=100')->assertStatus(400);
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'page', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/public/v1/projects/'.$project.'/issues?perPage=100&page=101', $headers)->assertOk()->assertJsonPath('data.maxOffset', 10000);
        $this->getJson('/api/public/v1/projects/'.$project.'/issues?perPage=100&page=102', $headers)->assertStatus(400);
        $this->getJson('/api/public/v1/projects/'.$project.'/issue-changes?perPage=1', $headers)->assertOk()->assertJsonPath('data.nextCursor', null)->assertJsonCount(1,'data.items');
    }
}
