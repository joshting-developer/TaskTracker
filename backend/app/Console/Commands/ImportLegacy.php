<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Repositories\RecordStore;
use App\Services\IssueRelationsUpgrade;
use App\Services\IssueTypesUpgrade;
use App\Services\ProjectAclUpgrade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;

class ImportLegacy extends Command
{
    protected $signature = 'tasktracker:import-legacy {file : 舊版 Node SQLite 的唯讀來源路徑}';
    protected $description = '只匯入空白 Laravel 資料庫, 保留舊資料及原帳密, 不匯入舊 session／重試快取';

    public function handle(): int
    {
        $path = realpath($this->argument('file'));
        if (!$path || !is_file($path) || $path === realpath(config('database.connections.sqlite.database'))) {
            $this->error('來源不存在或與目標相同');
            return self::FAILURE;
        }
        $source = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $source->exec('PRAGMA query_only=ON');
        $rows = $source->query('SELECT kind,id,payload FROM records ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
        try {
            DB::transaction(function () use ($rows) {
                if (DB::table('records')->exists() || User::exists()) throw new \RuntimeException('目標已有帳號或業務資料, 拒絕覆蓋；請另用空白資料庫／volume');
                $store = new RecordStore;
                foreach ($rows as $row) {
                    $value = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
                    if ($row['kind'] === 'accounts') {
                        if (strlen($value['salt'] ?? '') !== 32 || strlen($value['passwordHash'] ?? '') !== 128) throw new \RuntimeException('舊帳號雜湊格式不相容, 未匯入任何資料');
                        $user = new User;
                        $user->forceFill(['id' => $value['id'], 'name' => $value['name'], 'email' => $value['email'], 'password' => Hash::make(Str::random(64)), 'legacy_salt' => $value['salt'], 'legacy_hash' => $value['passwordHash']])->save();
                    } elseif ($row['kind'] !== 'sessions' && $row['kind'] !== 'requests' && !str_ends_with($row['kind'], ':requests')) {
                        $store->put($row['kind'], $row['id'], $value);
                    }
                }
                IssueRelationsUpgrade::run();
                IssueTypesUpgrade::run();
                ProjectAclUpgrade::run();
            });
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
        $this->info('舊資料已匯入, 來源未修改。請重新登入；舊帳號首次成功登入時升級密碼雜湊');
        return self::SUCCESS;
    }
}
