<?php

namespace Tests\Feature;

use App\Database\WorkspaceSqliteConnection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/** 實測SQLite讀取快照／寫鎖及HTTP白名單，不以memory推論並行。 */
class ReadTransactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * API唯讀白名單會啟用query_only；寫入仍用原有交易。
     *
     * @return void
     */
    public function test_api_reads_are_query_only_and_writes_restore_connection_state(): void
    {
        $connection = DB::connection();
        $this->assertInstanceOf(WorkspaceSqliteConnection::class, $connection);
        $this->postJson('/api/auth/register', [
            'name' => 'reader', 'email' => 'read-transaction@example.test',
            'password' => 'read-transaction-test', 'workspaceName' => '讀取交易',
        ])->assertOk();
        $modes = [];
        DB::listen(function ($event) use (&$modes) {
            $modes[] = (int) $event->connection->getPdo()->query('PRAGMA query_only')->fetchColumn();
        });
        foreach (['getWorkspace' => [], 'getTimeReport' => ['startDate' => '2026-11-01', 'endDate' => '2026-11-09']] as $operation => $input) {
            $modes = [];
            $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input])->assertOk();
            $this->assertContains(1, $modes);
            $this->assertSame(0, (int) $connection->getPdo()->query('PRAGMA query_only')->fetchColumn());
        }
        $modes = [];
        $this->postJson('/api/rpc/createProject', ['requestId' => (string) Str::uuid(), 'name' => '寫入仍可保存', 'code' => 'WRITE'])->assertOk();
        $this->assertNotContains(1, $modes);
        $this->assertDatabaseHas('projects', ['code' => 'WRITE']);
    }

    /**
     * 禁止誤寫、異常及巢狀交易離開後還原query_only與Laravel交易層數。
     *
     * @return void
     */
    public function test_read_transaction_rejects_writes_and_restores_after_failure(): void
    {
        $connection = DB::connection();
        $level = $connection->transactionLevel();
        try {
            $connection->readTransaction(fn () => $connection->statement('CREATE TABLE forbidden_read_write (id INTEGER)'));
            $this->fail('唯讀交易不應允許寫入');
        } catch (QueryException $error) {
            $this->assertSame('HY000', $error->getCode());
        }
        $this->assertSame($level, $connection->transactionLevel());
        $this->assertSame(0, (int) $connection->getPdo()->query('PRAGMA query_only')->fetchColumn());
        try {
            $connection->readTransaction(function () use ($connection) {
                $connection->readTransaction(fn () => $connection->selectOne('SELECT 1'));
                $this->assertSame(1, (int) $connection->getPdo()->query('PRAGMA query_only')->fetchColumn());
                throw new RuntimeException('read-failure');
            });
            $this->fail('唯讀交易必須傳遞用例例外');
        } catch (RuntimeException $error) {
            $this->assertSame('read-failure', $error->getMessage());
        }
        $this->assertSame($level, $connection->transactionLevel());
        $this->assertSame(0, (int) $connection->getPdo()->query('PRAGMA query_only')->fetchColumn());
        $connection->statement('CREATE TABLE allowed_after_read (id INTEGER)');
        $this->assertTrue($connection->getSchemaBuilder()->hasTable('allowed_after_read'));
    }

    /**
     * 獨立磁碟WAL兩連線：讀快照不擋writer；一般交易仍持有IMMEDIATE寫鎖。
     *
     * @return void
     */
    public function test_wal_read_snapshot_allows_other_writer_but_write_transaction_keeps_lock(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tt-read-lock-');
        try {
            $pdo = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $other = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $other->exec('PRAGMA busy_timeout=20');
            $pdo->exec('CREATE TABLE probe (id INTEGER PRIMARY KEY)');
            $pdo->exec('INSERT INTO probe VALUES (1)');
            $connection = new WorkspaceSqliteConnection($pdo, $path, '', ['driver' => 'sqlite', 'transaction_mode' => 'IMMEDIATE']);
            $connection->readTransaction(function () use ($connection, $other) {
                $this->assertSame(1, $connection->selectOne('SELECT COUNT(*) AS total FROM probe')->total);
                $other->exec('INSERT INTO probe VALUES (2)');
                $this->assertSame(1, $connection->selectOne('SELECT COUNT(*) AS total FROM probe')->total, '同交易保留一致快照');
            });
            $this->assertSame(2, $connection->selectOne('SELECT COUNT(*) AS total FROM probe')->total);
            $connection->transaction(function () use ($other) {
                try {
                    $other->exec('INSERT INTO probe VALUES (3)');
                    $this->fail('一般交易必須保留IMMEDIATE寫鎖');
                } catch (PDOException $error) {
                    $this->assertSame(5, $error->errorInfo[1]);
                }
            });
            $other->exec('INSERT INTO probe VALUES (3)');
            $this->assertSame(3, $connection->selectOne('SELECT COUNT(*) AS total FROM probe')->total);
            $this->assertSame(0, $connection->transactionLevel());
        } finally {
            unset($connection, $pdo, $other);
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
