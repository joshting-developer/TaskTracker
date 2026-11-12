<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;

/** 匯入現行Laravel SQLite到空MySQL；保留內部FK、公開ID、密碼雜湊及歷史。 */
class ImportSqlite extends Command
{
    protected $signature = 'tasktracker:import-sqlite {file : 現行Laravel SQLite唯讀來源} {--verify-only : 只比較來源與目標，不寫入}';

    protected $description = '將現行SQLite完整匯入空MySQL，逐表核對筆數及正規化內容；不修改來源';

    /**
     * 把舊SQLite備份逐表匯入目前資料庫；目標非空即拒絕，匯入後以內容摘要核對一致。
     *
     * @return int 指令結束碼
     */
    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('目標必須是MySQL');

            return self::FAILURE;
        }
        $path = realpath($this->argument('file'));
        if (! $path || ! is_file($path)) {
            $this->error('找不到SQLite來源');

            return self::FAILURE;
        }
        $source = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $source->exec('PRAGMA query_only=ON');
        $source->beginTransaction();
        try {
            if ($source->query('PRAGMA foreign_key_check')->fetch()) {
                throw new RuntimeException('來源外鍵驗證失敗');
            }
            $tables = $source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
            $tables = array_values(array_diff($tables, ['migrations', 'transaction_guards']));
            foreach (['users', 'workspaces', 'issues', 'time_entries'] as $required) {
                if (! in_array($required, $tables, true)) {
                    throw new RuntimeException('來源不是現行Laravel資料庫');
                }
            }
            $ordered = [];
            $pending = $tables;
            while ($pending) {
                $progress = false;
                foreach ($pending as $i => $table) {
                    if (! preg_match('/^[a-z_]+$/D', $table) || ! Schema::hasTable($table)) {
                        throw new RuntimeException('來源含不支援的資料表');
                    }
                    $parents = array_column(Schema::getForeignKeys($table), 'foreign_table');
                    if (array_diff($parents, [$table], $ordered)) {
                        continue;
                    }
                    $ordered[] = $table;
                    unset($pending[$i]);
                    $progress = true;
                }
                if (! $progress) {
                    throw new RuntimeException('無法建立外鍵匯入順序');
                }
            }
            // 同時只能有一個匯入者；啟動主API前離線執行，不關閉foreign_key_checks。
            if ((int) DB::selectOne("SELECT GET_LOCK('tasktracker-import-sqlite', 0) AS acquired")->acquired !== 1) {
                throw new RuntimeException('已有匯入程序');
            }
            try {
                $report = DB::transaction(function () use ($source, $ordered) {
                    if (! $this->option('verify-only')) {
                        foreach ($ordered as $table) {
                            if (DB::table($table)->exists()) {
                                throw new RuntimeException('目標已有資料，拒絕覆蓋：'.$table);
                            }
                        }
                    }
                    $report = [];
                    foreach ($ordered as $table) {
                        $columns = array_column(Schema::getColumns($table), null, 'name');
                        $rows = $source->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
                        $selfReferences = [];
                        foreach ($rows as &$row) {
                            foreach ($row as $field => &$value) {
                                if (! isset($columns[$field])) {
                                    throw new RuntimeException('目標缺少來源欄位：'.$table.'.'.$field);
                                }
                                $value = $this->stored($value, $columns[$field]['type_name']);
                            }
                            unset($value);
                        }
                        unset($row);
                        if (! $this->option('verify-only')) {
                            foreach ($rows as $row) {
                                if ($table === 'issues') {
                                    $selfReferences[] = ['id' => $row['id'], 'epic_id' => $row['epic_id'], 'parent_issue_id' => $row['parent_issue_id']];
                                    $row['epic_id'] = $row['parent_issue_id'] = null;
                                }
                                DB::table($table)->insert($row);
                            }
                            foreach ($selfReferences as $references) {
                                $id = $references['id'];
                                unset($references['id']);
                                DB::table('issues')->where('id', $id)->update($references);
                            }
                        }
                        $target = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
                        $expected = $this->digest($rows, $columns);
                        if (count($rows) !== count($target) || $expected !== $this->digest($target, $columns)) {
                            throw new RuntimeException('內容核對失敗：'.$table);
                        }
                        $report[] = [$table, count($rows), $expected];
                    }

                    return $report;
                });
            } finally {
                DB::select("SELECT RELEASE_LOCK('tasktracker-import-sqlite')");
            }
            $this->table(['table', 'rows', 'SHA-256（正規化內容）'], $report);
            $this->info($this->option('verify-only') ? '來源／目標核對一致' : '匯入完成，來源未修改；請保留原SQLite供回退');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('匯入／核對失敗：'.($error instanceof QueryException ? '資料庫約束或型別檢查失敗，交易已回滾' : $error->getMessage()));

            return self::FAILURE;
        } finally {
            $source->rollBack();
        }
    }

    /**
     * 依欄位型別把SQLite原值轉成目標資料庫可保存的值。
     *
     * @param  mixed  $value  SQLite讀出的原值
     * @param  string  $type  目標欄位型別
     * @return mixed 轉換後的值
     */
    private function stored(mixed $value, string $type): mixed
    {
        if ($value !== null && in_array($type, ['datetime', 'timestamp'], true)) {
            return CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d H:i:s.u');
        }

        return $value;
    }

    /**
     * JSON物件key順序不影響內容；數字／日期先依目標型別正規化。
     *
     * @param  array  $rows  要比對的資料列
     * @param  array  $columns  納入摘要的欄位
     * @return string 內容的sha256摘要
     */
    private function digest(array $rows, array $columns): string
    {
        $lines = [];
        foreach ($rows as $row) {
            ksort($row);
            foreach ($row as $field => &$value) {
                $type = $columns[$field]['type_name'];
                $value = $this->stored($value, $type);
                if ($value !== null && str_contains($type, 'int')) {
                    $value = (int) $value;
                }
                if ($value !== null && $type === 'json') {
                    $value = $this->canonical(json_decode($value, flags: JSON_THROW_ON_ERROR));
                }
            }
            unset($value);
            $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * 遞迴排序陣列鍵值，讓兩邊資料用相同順序計算摘要。
     *
     * @param  mixed  $value  待正規化的值
     * @return mixed 排序後的值
     */
    private function canonical(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $items = get_object_vars($value);
            ksort($items);

            return (object) array_map($this->canonical(...), $items);
        }

        return is_array($value) ? array_map($this->canonical(...), $value) : $value;
    }
}
