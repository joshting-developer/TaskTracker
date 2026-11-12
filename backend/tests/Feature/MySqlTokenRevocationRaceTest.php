<?php

namespace Tests\Feature;

use App\Database\WriteLocks;
use App\Models\IntegrationAudit;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\PersonalTokenCases;
use Tests\MySqlTestCase;

/** 獨立程序／PDO確認鎖後撤銷重驗；fixture先提交，不以單連線模擬競態。 */
class MySqlTokenRevocationRaceTest extends MySqlTestCase
{
    use PersonalTokenCases;

    /**
     * 撤銷先持有User鎖，舊Token請求等鎖後必須重讀並拒絕。
     *
     * @return void
     */
    public function test_revoked_token_waiting_on_account_lock_cannot_execute_or_append_audit(): void
    {
        $identity = $this->tokenRegister();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'race', 'code' => 'REV', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $token = $this->tokenIssue(['abilities' => ['write']]);
        $before = IntegrationAudit::count();
        $prefix = tempnam(sys_get_temp_dir(), 'tt-token-race-');
        $process = null;
        try {
            chmod($prefix, 0600);
            file_put_contents($prefix, json_encode(['plain' => $token['plainTextToken'], 'project' => $project['id'], 'ready' => $prefix.'.ready', 'result' => $prefix.'.result'], JSON_THROW_ON_ERROR));
            DB::beginTransaction();
            app(WriteLocks::class)->account($identity['user']['id']);
            app(WriteLocks::class)->workspace($identity['workspace']['id']);
            PersonalAccessToken::whereKey($token['token']['id'])->update(['revoked_at' => now()]);
            $process = new Process([PHP_BINARY, base_path('tools/token-revocation-worker.php'), $prefix]);
            $process->setTimeout(15)->start();
            $deadline = microtime(true) + 8;
            while (! is_file($prefix.'.ready')) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    $this->fail('競態worker未到達帳號鎖：'.$process->getErrorOutput());
                }
                usleep(10000);
            }
            DB::commit();
            $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            $this->assertSame(['ok' => false, 'code' => 'UNAUTHENTICATED'], json_decode(file_get_contents($prefix.'.result'), true));
            $this->assertSame($before, IntegrationAudit::count());
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            if ($process?->isRunning()) {
                $process->stop();
            }
            foreach (glob($prefix.'*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
