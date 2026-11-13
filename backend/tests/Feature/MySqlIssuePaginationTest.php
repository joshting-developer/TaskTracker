<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\IssuePaginationCases;
use Tests\MySqlTestCase;

/** 任務列表分頁界限HTTP回歸 */
class MySqlIssuePaginationTest extends MySqlTestCase
{
    use DatabaseTransactions, IssuePaginationCases;

    /**
     * 真MySQL確認混合方向與既有cursor保留, 不只檢查migration檔案存在
     *
     * @return void
     */
    public function test_sort_indexes_match_query_without_duplicate_signatures(): void
    {
        $indexes = [];
        foreach (DB::select('SHOW INDEX FROM issues') as $row) {
            $indexes[$row->Key_name][] = $row->Column_name.':'.$row->Collation;
        }
        $this->assertSame(['workspace_id:A', 'project_id:A', 'updated_at:D', 'identifier:A', 'public_id:A'], $indexes['issues_list_updated_desc']);
        $this->assertSame(['workspace_id:A', 'project_id:A', 'created_at:D', 'updated_at:D', 'identifier:A', 'public_id:A'], $indexes['issues_list_created_desc']);
        $this->assertSame(['workspace_id:A', 'project_id:A', 'updated_at:A', 'id:A'], $indexes['issues_change_cursor']);
        $signatures = array_map(fn ($columns) => implode(',', $columns), $indexes);
        $this->assertCount(count($signatures), array_unique($signatures));
    }
}
