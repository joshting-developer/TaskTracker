<?php

namespace Tests\Feature;

use App\Domain\DomainError;
use App\Models\Board;
use App\Models\Issue;
use App\Models\User;
use App\Repositories\EntityDefinitions;
use App\Repositories\LegacyRecordStore;
use App\Repositories\RecordStore;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** 驗證關聯儲存、租戶外鍵與往返遷移；只使用記憶體資料庫。 */
class RelationalStorageTest extends TestCase
{
    use RefreshDatabase;

    /** 建立含留言、看板、待辦及重試結果的真實API資料。
     * @return RecordStore
     */
    private function seedWorkspace(): RecordStore
    {
        app(DemoSeeder::class)->seed(password: 'seed-test-password');
        $this->postJson('/api/auth/login', [
            'email' => DemoSeeder::EMAIL,
            'password' => 'seed-test-password',
        ])->assertOk();
        $tenant = (new RecordStore)->forWorkspace(DemoSeeder::WORKSPACE_ID);
        $project = $tenant->all('projects')[0];
        $issue = $tenant->all('issues')[1];
        $this->rpc('addComment', [
            'id' => $issue['id'],
            'expectedRevision' => $issue['revision'],
            'content' => '遷移後保留留言',
        ]);
        $this->rpc('createBoard', [
            'projectId' => $project['id'],
            'name' => '遷移看板',
            'selectedIssueIds' => [$issue['id']],
        ]);
        $this->rpc('createTodo', ['title' => '可追蹤的速記']);

        return $tenant;
    }

    /** 經真實HTTP路由寫入，驗證權限與回應成功。
     * @param  string  $operation  操作名稱
     * @param  array  $input  操作欄位
     * @return array
     */
    private function rpc(string $operation, array $input): array
    {
        return $this->postJson('/api/rpc/'.$operation, [
            'requestId' => (string) Str::uuid(),
            ...$input,
        ])->assertOk()->json('data');
    }

    /** 收集所有業務DTO，物件鍵順序不影響資料完整性。
     * @return array
     */
    private function snapshot(): array
    {
        $store = new RecordStore;
        $result = [];
        foreach (EntityDefinitions::ENTITIES as $kind => $definition) {
            if (in_array($definition['scope'], [null, 'global'], true)) {
                $result[$kind] = $store->all($kind);
            } else {
                foreach ($store->all('workspaces') as $workspace) {
                    $result[$workspace['id']][$kind] = $store->forWorkspace($workspace['id'])->all($kind);
                }
            }
        }

        return $result;
    }

    /** 查詢核心欄位與Eloquent關聯，確認正常寫入不會回到JSON records。
     * @return void
     */
    public function test_business_writes_use_columns_relations_and_bounded_queries(): void
    {
        $tenant = $this->seedWorkspace();
        $this->assertSame(0, DB::table('records')->count());
        $this->assertFalse(Schema::hasColumn('issues', 'payload'));
        $issue = Issue::whereNotNull('parent_issue_id')->firstOrFail();
        $this->assertSame($issue->project_id, $issue->parent->project_id);
        $this->assertSame($issue->project_id, $issue->status->project_id);
        $this->assertNotNull($issue->assignee->user);
        $this->assertSame(1, Board::firstOrFail()->issues()->count());
        $this->assertTrue(Issue::whereHas('relatedIssues')->exists());
        $this->assertSame(1, User::findOrFail(DemoSeeder::USER_ID)->workspaces()->count());
        $this->assertSame(14, DB::table('issues')->count());
        $this->assertGreaterThan(6, DB::table('time_entries')->count());
        $this->assertSame(1, DB::table('todos')->count());
        $this->assertGreaterThan(0, DB::table('operation_receipts')->count());
        $this->assertNotEmpty(DB::table('issue_comments')->first());
        $this->assertEmpty(array_filter($tenant->all('issues'), fn ($item) => isset($item['legacy_attributes'])));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $tenant->all('issues');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(20, $count, '任務數增加不可逐任務追加關聯查詢');
    }

    /** 讓資料庫直接拒絕同租戶跨專案與跨租戶外鍵，避免只依賴API檢查。
     * @return void
     */
    public function test_database_rejects_cross_scope_references_and_orphan_deletes(): void
    {
        $this->seedWorkspace();
        $first = Issue::firstOrFail();
        $otherProject = Issue::where('project_id', '!=', $first->project_id)->firstOrFail();
        $other = $this->postJson('/api/auth/register', [
            'name' => '隔離帳號',
            'email' => 'isolated@example.test',
            'password' => 'test-local-password',
            'workspaceName' => '另一空間',
        ])->assertOk()->json('data');
        $otherMember = DB::table('workspace_members')->where('user_id', $other['user']['id'])->value('id');
        $board = Board::firstOrFail();
        $attempts = [fn () => DB::table('issues')->where('id', $first->id)->update(['assignee_id' => $otherMember]), fn () => DB::table('issues')->where('id', $first->id)->update(['state_id' => $otherProject->state_id]), fn () => DB::table('issues')->where('id', $first->id)->update(['parent_issue_id' => $otherProject->id]), fn () => DB::table('board_issues')->insert([
            'workspace_id' => $board->workspace_id,
            'project_id' => $board->project_id,
            'board_id' => $board->id,
            'issue_id' => $otherProject->id,
            'position' => 1,
        ]), fn () => DB::table('issue_relations')->insert([
            'workspace_id' => $first->workspace_id,
            'project_id' => $first->project_id,
            'issue_id' => $first->id,
            'related_issue_id' => $otherProject->id,
            'position' => 1,
        ]), fn () => DB::table('projects')->where('id', $first->project_id)->delete()];
        foreach ($attempts as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('外鍵必須拒絕不合法關聯');
            } catch (QueryException $failure) {
                $this->assertStringContainsString('FOREIGN KEY', $failure->getMessage());
            }
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    /** 回填保留所有DTO；回退前匯出最新資料，再升級仍保留修改。
     * @return void
     */
    public function test_backfill_and_rollback_preserve_business_data_and_archive(): void
    {
        $this->seedWorkspace();
        $before = $this->snapshot();
        $migration = require database_path('migrations/2026_11_04_000000_create_relational_business_tables.php');
        Schema::dropIfExists('issue_external_refs'); // 後續migration的FK表；正常rollback會先撤它
        $migration->down();
        $this->assertFalse(Schema::hasTable('issues'));
        // 舊重試payload沒有id，真正識別碼位於records.id。
        $receipt = DB::table('records')->where('kind', 'w:'.DemoSeeder::WORKSPACE_ID.':requests')->first();
        $payload = json_decode($receipt->payload, true, flags: JSON_THROW_ON_ERROR);
        unset($payload['id']);
        DB::table('records')->where('sequence', $receipt->sequence)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        $archive = DB::table('records')->orderBy('kind')->orderBy('id')->get()->toJson();
        $migration->up();
        $this->assertEquals($before, $this->snapshot());
        $this->assertSame($archive, DB::table('records')->orderBy('kind')->orderBy('id')->get()->toJson());
        $tenant = (new RecordStore)->forWorkspace(DemoSeeder::WORKSPACE_ID);
        $issue = $tenant->all('issues')[1];
        $this->rpc('updateIssue', [
            'id' => $issue['id'],
            'expectedRevision' => $issue['revision'],
            'title' => '升級後的修改',
        ]);
        $this->rpc('createTodo', ['title' => '升級後新資料']);
        $this->assertSame($archive, DB::table('records')->orderBy('kind')->orderBy('id')->get()->toJson());
        $latest = $this->snapshot();
        $migration->down();
        $migration->up();
        $this->assertEquals($latest, $this->snapshot());
        $this->rpc('getWorkspace', []);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    /** 無法回填的孤兒資料必須回滾整個migration，來源保持可恢復。
     * @return void
     */
    public function test_failed_backfill_rolls_back_schema_and_preserves_source(): void
    {
        $this->seedWorkspace();
        $migration = require database_path('migrations/2026_11_04_000000_create_relational_business_tables.php');
        Schema::dropIfExists('issue_external_refs'); // 後續migration的FK表；正常rollback會先撤它
        $migration->down();
        $archive = (new LegacyRecordStore)->forWorkspace(DemoSeeder::WORKSPACE_ID);
        $issue = $archive->all('issues')[1];
        $archive->put('issues', $issue['id'], [
            ...$issue,
            'projectId' => 'missing-project',
        ]);
        $before = DB::table('records')->orderBy('kind')->orderBy('id')->get()->toJson();
        try {
            DB::transaction(fn () => $migration->up());
            $this->fail('孤兒資料不可靜默遺失');
        } catch (DomainError $failure) {
            $this->assertStringContainsString('關聯資料不存在', $failure->getMessage());
        }
        $this->assertFalse(Schema::hasTable('workspaces'));
        $this->assertSame($before, DB::table('records')->orderBy('kind')->orderBy('id')->get()->toJson());
        $archive->put('issues', $issue['id'], $issue);
        $migration->up();
        $this->assertSame(14, DB::table('issues')->count());
    }
}
