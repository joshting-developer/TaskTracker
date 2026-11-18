<?php

namespace Tests\Feature;

use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RelationalMigrationRoundTrip;
use Tests\TestCase;

/** MySQL DDL 會直接提交, 往返測試不包在測試資料交易內。 */
class TodoMigrationTest extends TestCase
{
    use DatabaseMigrations, RelationalMigrationRoundTrip;

    /**
     * 舊待辦與 Markdown 原稿經完整回退／恢復後保持相同內容
     *
     * @return void
     */
    public function test_markdown_migration_round_trip_preserves_legacy_and_new_content(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'migration', 'email' => 'todo-migration@example.test', 'password' => 'migration-password', 'workspaceName' => 'migration'])->assertOk()->json('data');
        $tenant = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        $ids = [];
        foreach ([['title' => '舊標題', 'note' => '舊補充'], ['content' => "# 新速記\n\n- [ ] 一起確認"]] as $input) {
            $ids[] = $this->postJson('/api/rpc/createTodo', ['requestId' => (string) Str::uuid(), ...$input])->assertOk()->json('data.id');
        }
        $before = array_map(fn ($id) => $tenant->require('todos', $id), $ids);
        $relational = require database_path('migrations/2026_11_04_000000_create_relational_business_tables.php');
        $this->rollbackLaterMigrations();
        $relational->down();
        $relational->up();
        $this->restoreLaterMigrations();
        $this->assertDtoSame($before, array_map(fn ($id) => $tenant->require('todos', $id), $ids));
        $this->assertSame([], json_decode(DB::table('todos')->where('public_id', $ids[1])->value('legacy_attributes'), true));
    }
}
