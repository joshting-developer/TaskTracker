<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Tests\MySqlTestCase;

/** 獨立測試schema，驗證現行SQLite的完整匯入／核對／rollback與拒絕覆蓋。 */
class MySqlImportTest extends MySqlTestCase
{
    protected string $testDatabase = 'tasktracker_test_import';

    public function test_import_preserves_data_and_rolls_back_failures_without_touching_source(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $path = tempnam(sys_get_temp_dir(), 'tt-mysql-import-');
        try {
            foreach ([['migrate', '--force'], ['tasktracker:seed-demo']] as $arguments) {
                $process = new Process([PHP_BINARY, base_path('artisan'), ...$arguments], base_path(), ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'DB_URL' => '', 'TASKTRACKER_MYSQL_TEST' => '0', 'TASKTRACKER_SEED_PASSWORD' => 'import-fixture-password']);
                $process->setTimeout(30);
                $this->assertSame(0, $process->run(), $process->getOutput().$process->getErrorOutput());
            }
            $source = new PDO('sqlite:'.$path);
            $source->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $row = $source->query('SELECT id,title FROM issues ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $update = $source->prepare('UPDATE issues SET title=? WHERE id=?');
            $update->execute([str_repeat('x', 300), $row['id']]);
            $this->artisan('tasktracker:import-sqlite', ['file' => $path])->assertExitCode(1);
            $this->assertSame(0, DB::table('users')->count(), '晚期插入失敗也須回滾先前帳號');
            $this->assertSame(0, DB::table('workspaces')->count());
            $update->execute([$row['title'], $row['id']]);
            $source->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $hash = hash_file('sha256', $path);
            $this->artisan('tasktracker:import-sqlite', ['file' => $path])->assertExitCode(0);
            $this->assertSame($hash, hash_file('sha256', $path));
            foreach (['users', 'workspaces', 'projects', 'issues', 'time_entries', 'audit_events'] as $table) {
                $this->assertSame((int) $source->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(), DB::table($table)->count());
            }
            $this->assertSame($source->query('SELECT password FROM users ORDER BY id LIMIT 1')->fetchColumn(), DB::table('users')->orderBy('id')->value('password'));
            $this->artisan('tasktracker:import-sqlite', ['file' => $path, '--verify-only' => true])->assertExitCode(0);
            $this->artisan('tasktracker:import-sqlite', ['file' => $path])->assertExitCode(1);
            $update->execute(['來源已異動', $row['id']]);
            $this->artisan('tasktracker:import-sqlite', ['file' => $path, '--verify-only' => true])->assertExitCode(1);
            $this->assertSame($row['title'], DB::table('issues')->where('id', $row['id'])->value('title'));
        } finally {
            unset($source, $update);
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
