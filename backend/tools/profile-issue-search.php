<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/** MySQL隔離合成表量測搜尋計畫／COUNT／data與ngram語意, 不讀現用卡片 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'tasktracker_test' || config('database.connections.mysql.username') !== 'tasktracker_test') {
    throw new RuntimeException('Only isolated tasktracker_test MySQL is allowed');
}
$table = 'tt_search_probe_'.bin2hex(random_bytes(5));
$full = $table.'_ng';
/** 多次暖機後的DB往返分位數, 非正式HTTP／RUM
 * @param  string  $sql  固定合成表SQL
 * @param  array  $bindings  合成值
 * @return array p50與p95毫秒
 */
function sampleSearch(string $sql, array $bindings): array
{
    $times = [];
    for ($n = 0; $n < 35; $n++) {
        $start = hrtime(true);
        DB::select($sql, $bindings);
        if ($n >= 5) {
            $times[] = (hrtime(true) - $start) / 1e6;
        }
    }
    sort($times);

    return ['samples' => count($times), 'p50Ms' => round($times[14], 3), 'p95Ms' => round($times[28], 3)];
}
try {
    DB::statement("CREATE TABLE `$table` (id BIGINT PRIMARY KEY, workspace_id BIGINT NOT NULL, project_id BIGINT NOT NULL, identifier VARCHAR(100) NOT NULL, title VARCHAR(180) NOT NULL, description TEXT NOT NULL, updated_at DATETIME NOT NULL, UNIQUE KEY issue_identifier(workspace_id,project_id,identifier), KEY scoped(workspace_id,project_id)) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    $sizes = [];
    $insertTimes = [];
    foreach ([1000, 10000, 50000] as $size) {
        $previous = $size === 1000 ? 0 : ($size === 10000 ? 1000 : 10000);
        $start = hrtime(true);
        for ($from = $previous + 1; $from <= $size; $from += 500) {
            $rows = [];
            for ($id = $from; $id <= min($size, $from + 499); $id++) {
                $rows[] = ['id' => $id, 'workspace_id' => 1, 'project_id' => 1, 'identifier' => 'PERF-'.$id, 'title' => '合成會員任務 '.$id, 'description' => 'API punctuation 100%_! 單字中 '.str_repeat('synthetic ', 10), 'updated_at' => '2026-11-13 00:00:00'];
            }
            DB::table($table)->insert($rows);
        }
        $insertTimes[] = ['rows' => $size - $previous, 'elapsedMs' => round((hrtime(true) - $start) / 1e6, 2)];
        DB::statement("ANALYZE TABLE `$table`");
        $old = 'workspace_id=1 AND project_id=1 AND (LOWER(title) LIKE ? OR LOWER(identifier) LIKE ? OR LOWER(description) LIKE ?)';
        $exact = 'workspace_id=1 AND project_id=1 AND identifier=?';
        $rows = [];
        foreach (['contains' => [$old, ['%perf-123%', '%perf-123%', '%perf-123%']], 'exact' => [$exact, ['PERF-123']], 'prefix' => ['workspace_id=1 AND project_id=1 AND identifier LIKE ?', ['PERF-123%']]] as $kind => [$where, $bindings]) {
            foreach (['count' => "SELECT COUNT(*) FROM `$table` WHERE $where", 'data' => "SELECT id,identifier,title FROM `$table` WHERE $where ORDER BY updated_at DESC,identifier,id LIMIT 20"] as $query => $sql) {
                $rows[$kind][$query] = [...sampleSearch($sql, $bindings), 'plan' => DB::select('EXPLAIN ANALYZE '.$sql, $bindings)];
            }
        }
        $sizes[] = ['rows' => $size, 'queries' => $rows];
    }
    DB::statement("CREATE TABLE `$full` LIKE `$table`");
    DB::statement("ALTER TABLE `$full` ADD FULLTEXT KEY text_ng(title,description) WITH PARSER ngram");
    $start = hrtime(true);
    DB::statement("INSERT INTO `$full` SELECT * FROM `$table`");
    $ngInsert = (hrtime(true) - $start) / 1e6;
    $literal = [];
    foreach (['中', '會員', '100%_!', 'API'] as $term) {
        $literal[] = ['term' => $term, 'containsCount' => DB::selectOne("SELECT COUNT(*) AS n FROM `$full` WHERE LOWER(description) LIKE ? OR LOWER(title) LIKE ?", ['%'.mb_strtolower($term).'%', '%'.mb_strtolower($term).'%'])->n,
            'ngramCount' => DB::selectOne("SELECT COUNT(*) AS n FROM `$full` WHERE MATCH(title,description) AGAINST (? IN BOOLEAN MODE)", ['"'.$term.'"'])->n];
    }
    $start = hrtime(true);
    DB::statement("UPDATE `$table` SET description=CONCAT(description,' changed') WHERE id<=1000");
    $plainUpdate = (hrtime(true) - $start) / 1e6;
    $start = hrtime(true);
    DB::statement("UPDATE `$full` SET description=CONCAT(description,' changed') WHERE id<=1000");
    $ngUpdate = (hrtime(true) - $start) / 1e6;
    echo json_encode(['engine' => DB::selectOne('SELECT VERSION() AS v')->v, 'topology' => 'isolated Docker MySQL, in-container SQL; warm DB RTT, not HTTP or production p95', 'sizes' => $sizes, 'insertBatches' => $insertTimes,
        'ngram' => ['size' => DB::selectOne('SELECT @@ngram_token_size AS n')->n, 'insert50000Ms' => round($ngInsert, 2), 'literalComparison' => $literal, 'update1000PlainMs' => round($plainUpdate, 2), 'update1000NgramMs' => round($ngUpdate, 2)]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    DB::statement("DROP TABLE IF EXISTS `$full`");
    DB::statement("DROP TABLE IF EXISTS `$table`");
}
