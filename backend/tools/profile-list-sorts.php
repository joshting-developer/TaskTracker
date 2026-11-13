<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/** MySQL隔離合成表量測列表排序／COUNT／深分頁與索引寫入成本, 不讀現用卡片 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test') {
    throw new RuntimeException('Only isolated tasktracker_test MySQL is allowed');
}
$table = 'tt_sort_probe_'.bin2hex(random_bytes(5));
/** Compare the same bounded SQL after five warmups, thirty samples.
 * @param  string  $sql  SQL on the synthetic probe table
 * @return array p50/p95 milliseconds and actual execution plan
 */
function sortSamples(string $sql): array
{
    $times = [];
    for ($i = 0; $i < 35; $i++) {
        $start = hrtime(true);
        DB::select($sql);
        if ($i >= 5) {
            $times[] = (hrtime(true) - $start) / 1e6;
        }
    }
    sort($times);

    return ['p50Ms' => round($times[14], 3), 'p95Ms' => round($times[28], 3), 'plan' => DB::select('EXPLAIN ANALYZE '.$sql)];
}
try {
    $inventory = [];
    foreach (['issues', 'boards', 'todos', 'time_entries', 'audit_events', 'integration_audits'] as $name) {
        $inventory[$name] = DB::select("SHOW INDEX FROM `$name`");
    }
    DB::statement("CREATE TABLE `$table` (id BIGINT PRIMARY KEY,workspace_id BIGINT NOT NULL,project_id BIGINT NOT NULL,identifier VARCHAR(255) NOT NULL,public_id VARCHAR(200) NOT NULL,title VARCHAR(255) NOT NULL,priority VARCHAR(255) NOT NULL,due_date DATE NULL,created_at DATETIME(6) NULL,updated_at DATETIME(6) NULL,UNIQUE KEY identifier_unique(workspace_id,project_id,identifier),KEY issues_change_cursor(workspace_id,project_id,updated_at,id)) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $orders = ['updatedDesc' => 'updated_at DESC,identifier,public_id', 'updatedAsc' => 'updated_at ASC,identifier,public_id', 'createdDesc' => 'created_at DESC,updated_at DESC,identifier,public_id', 'createdAsc' => 'created_at ASC,updated_at DESC,identifier,public_id',
        'priority' => "CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END ASC,updated_at DESC,identifier,public_id", 'due' => 'CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC,due_date ASC,updated_at DESC,identifier,public_id'];
    $indexes = ['updated_desc' => 'workspace_id,project_id,updated_at DESC,identifier ASC,public_id ASC', 'created_desc' => 'workspace_id,project_id,created_at DESC,updated_at DESC,identifier ASC,public_id ASC'];
    $results = [];
    $previous = 0;
    foreach ([1000, 10000, 50000] as $size) {
        for ($first = $previous + 1; $first <= $size; $first += 500) {
            $rows = [];
            for ($id = $first; $id <= min($size, $first + 499); $id++) {
                $rows[] = ['id' => $id, 'workspace_id' => 1, 'project_id' => 1, 'identifier' => 'PERF-'.$id, 'public_id' => sprintf('00000000-0000-0000-0000-%012d', $id), 'title' => 'Synthetic '.$id, 'priority' => ['urgent', 'high', 'normal', 'low'][$id % 4], 'due_date' => $id % 4 ? '2026-11-'.sprintf('%02d', ($id % 28) + 1) : null, 'created_at' => '2026-10-'.sprintf('%02d', ($id % 28) + 1).' 00:00:00', 'updated_at' => '2026-11-'.sprintf('%02d', ($id % 28) + 1).' 00:00:00'];
            }
            DB::table($table)->insert($rows);
        }
        $previous = $size;
        DB::statement("ANALYZE TABLE `$table`");
        $before = [];
        $after = [];
        $select = "SELECT id,identifier,public_id,title FROM `$table` WHERE workspace_id=1 AND project_id=1";
        foreach ($orders as $key => $order) {
            $before[$key] = sortSamples($select.' ORDER BY '.$order.' LIMIT 20');
        }
        $before['deep'] = sortSamples($select.' ORDER BY '.$orders['updatedDesc'].' LIMIT 20 OFFSET '.min(10000, max(0, $size - 20)));
        $before['count'] = sortSamples("SELECT COUNT(*) FROM `$table` WHERE workspace_id=1 AND project_id=1");
        $start = hrtime(true);
        DB::statement("UPDATE `$table` SET title=CONCAT('Before ',id),updated_at='2026-11-13 00:00:00' WHERE id<=1000");
        $plainWrite = (hrtime(true) - $start) / 1e6;
        // Restore the dataset before adding indexes, so before/after reads use identical rows.
        DB::statement("UPDATE `$table` SET title=CONCAT('Synthetic ',id),updated_at=CONCAT('2026-11-',LPAD(MOD(id,28)+1,2,'0'),' 00:00:00') WHERE id<=1000");
        foreach ($indexes as $key => $cols) {
            DB::statement("CREATE INDEX `$key` ON `$table` ($cols)");
        }
        DB::statement("ANALYZE TABLE `$table`");
        foreach ($orders as $key => $order) {
            $after[$key] = sortSamples($select.' ORDER BY '.$order.' LIMIT 20');
        }
        $after['deep'] = sortSamples($select.' ORDER BY '.$orders['updatedDesc'].' LIMIT 20 OFFSET '.min(10000, max(0, $size - 20)));
        $after['count'] = sortSamples("SELECT COUNT(*) FROM `$table` WHERE workspace_id=1 AND project_id=1");
        $start = hrtime(true);
        DB::statement("UPDATE `$table` SET title=CONCAT('Before ',id),updated_at='2026-11-13 00:00:00' WHERE id<=1000");
        $indexedWrite = (hrtime(true) - $start) / 1e6;
        DB::statement("UPDATE `$table` SET title=CONCAT('Synthetic ',id),updated_at=CONCAT('2026-11-',LPAD(MOD(id,28)+1,2,'0'),' 00:00:00') WHERE id<=1000");
        $results[] = ['rows' => $size, 'before' => $before, 'after' => $after, 'write1000BeforeMs' => round($plainWrite, 3), 'write1000AfterMs' => round($indexedWrite, 3)];
        foreach ($indexes as $key => $cols) {
            DB::statement("DROP INDEX `$key` ON `$table`");
        }
    }
    // EXPLAIN of other high-frequency scopes uses the real schema and synthetic IDs only.
    $other = [];
    foreach (['boardsActive' => 'SELECT id,name FROM boards WHERE workspace_id=1 AND project_id=1 AND archived_at IS NULL ORDER BY name,public_id LIMIT 20', 'boardsArchived' => 'SELECT id,name FROM boards WHERE workspace_id=1 AND project_id=1 AND archived_at IS NOT NULL ORDER BY archived_at DESC,public_id LIMIT 20', 'todosMine' => "SELECT id,title FROM todos WHERE workspace_id=1 AND assignee_id=1 AND state='open' ORDER BY updated_at DESC,public_id LIMIT 20", 'todosSent' => "SELECT id,title FROM todos WHERE workspace_id=1 AND created_by_id=1 AND initial_assignee_id!=1 AND state='open' ORDER BY updated_at DESC,public_id LIMIT 20", 'time' => 'SELECT public_id,minutes FROM time_entries WHERE workspace_id=1 AND project_id=1 AND issue_id=1 AND voided_at IS NULL ORDER BY work_date DESC,public_id LIMIT 20', 'history' => "SELECT event_data FROM audit_events WHERE workspace_id=1 AND entity_type='issues' AND entity_id=1 AND stream='history' ORDER BY position DESC,id DESC LIMIT 20", 'audit' => "SELECT id FROM integration_audits WHERE workspace_id=1 AND actor_user_id='synthetic' ORDER BY id DESC LIMIT 20"] as $key => $sql) {
        $other[$key] = DB::select('EXPLAIN ANALYZE '.$sql);
    }
    echo json_encode(['engine' => DB::selectOne('SELECT VERSION() AS v')->v, 'samples' => 30, 'topology' => 'isolated MySQL Docker SQL, not production HTTP/RUM', 'inventory' => $inventory, 'sizes' => $results, 'otherPlans' => $other], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    DB::statement("DROP TABLE IF EXISTS `$table`");
}
