<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BoardRuleCases;
use Tests\MySqlTestCase;

/** 真實隔離 MySQL 驗證相關子查詢及原子收錄 */
class MySqlBoardRulesTest extends MySqlTestCase
{
    use BoardRuleCases;

    /** 1001 張合成卡片的規則計數與分頁不在 PHP 展開, 查詢數不隨頁大小成長
     * @return void
     */
    public function test_large_automatic_board_keeps_sql_pagination_and_constant_query_count(): void
    {
        [, $project, $base] = $this->ruleFixture();
        $issue = $this->ruleRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Bulk source'])->assertOk()->json('data');
        $source = (array) DB::table('issues')->where('public_id', $issue['id'])->first();
        unset($source['id']);
        for ($batch = 0; $batch < 10; $batch++) {
            $rows = [];
            for ($index = 0; $index < 100; $index++) {
                $number = $batch * 100 + $index + 1;
                $rows[] = [...$source, 'public_id' => (string) Str::uuid(), 'identifier' => 'RULE-B'.$number, 'title' => 'Synthetic '.$number, 'storage_position' => $number + 1];
            }
            DB::table('issues')->insert($rows);
        }
        $board = $this->ruleRpc('createBoard', ['projectId' => $project['id'], 'name' => 'Bulk', 'autoInclude' => ['types' => ['task']]])->assertOk()->json('data');
        $path = $base.'/boards/'.$board['id'];
        $this->getJson($path)->assertOk()->assertJsonPath('data.issueCount', 1001)->assertJsonPath('data.closedCount', 0);
        $counts = [];
        DB::enableQueryLog();
        try {
            foreach ([1, 100] as $size) {
                DB::flushQueryLog();
                $this->getJson($path.'/issues?perPage='.$size)->assertOk()->assertJsonPath('data.total', 1001)->assertJsonCount($size, 'data.items');
                $queries = DB::getQueryLog();
                $counts[] = count($queries);
                $this->assertNotEmpty(array_filter($queries, fn ($query) => str_contains($query['query'], 'board_rules') && str_contains(strtolower($query['query']), 'limit '.$size)));
                $this->assertEmpty(array_filter($queries, fn ($query) => str_contains($query['query'], 'issue_comments') || str_contains($query['query'], 'audit_events')));
            }
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->getJson($path.'/issues?perPage=100&page=11')->assertOk()->assertJsonCount(1, 'data.items');
        $this->postJson($base.'/board-preview', ['autoInclude' => ['types' => ['task']]])->assertOk()->assertJsonPath('data.total', 1001)->assertJsonCount(20, 'data.items');
    }
}
