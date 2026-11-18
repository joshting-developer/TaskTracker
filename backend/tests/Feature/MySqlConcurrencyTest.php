<?php

namespace Tests\Feature;

use App\Database\WriteLocks;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AccountService;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** 各worker真實獨立PDO，fixture先commit；不以單連線transaction假裝競態。 */
class MySqlConcurrencyTest extends TestCase
{
    /**
     * 透過正式HTTP建立並提交競態測試用的帳號、專案及卡片。
     *
     * @return array{0:array,1:array,2:array} 身分、專案及卡片寫入結果
     */
    private function fixture(): array
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'race', 'email' => Str::uuid().'@example.test', 'password' => 'mysql-race-password', 'workspaceName' => 'race'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['requestId' => (string) Str::uuid(), 'name' => 'race', 'code' => 'RACE'])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['requestId' => (string) Str::uuid(), 'projectId' => $project['id'], 'title' => 'race'])->assertOk()->json('data');

        return [$identity, $project, $issue];
    }

    /** 同版本並行修改條件與排除只有一筆成功, 不混合兩份收錄設定
     * @return void
     */
    public function test_parallel_board_rules_keep_one_atomic_revision(): void
    {
        [$identity, $project, $issue] = $this->fixture();
        $board = $this->postJson('/api/rpc/createBoard', ['projectId' => $project['id'], 'name' => 'Rule race', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $inputs = array_map(fn ($type) => ['id' => $board['id'], 'name' => $type, 'expectedRevision' => 1, 'autoInclude' => ['types' => [$type]], 'excludedIssueIds' => $type === 'task' ? [$issue['id']] : [], 'requestId' => (string) Str::uuid()], ['task', 'bug']);
        $results = $this->race($identity, 'updateBoard', $inputs);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertSame(['REVISION_CONFLICT'], array_values(array_column(array_filter($results, fn ($result) => ! $result['ok']), 'code')));
        $saved = $this->getJson('/api/v1/projects/'.$project['id'].'/boards/'.$board['id'])->assertOk()->assertJsonPath('data.revision', 2)->json('data');
        $this->assertSame([$saved['name']], $saved['autoInclude']['types']);
        $this->assertSame($saved['name'] === 'task' ? [$issue['id']] : [], $saved['excludedIssueIds']);
    }

    /**
     * 啟動獨立寫入程序，全部ready後才同時釋放；finally只清理本次檔案及程序。
     *
     * @param  array  $identity  已提交的帳號及Workspace身分
     * @param  string  $operation  競爭執行的RPC名稱
     * @param  array  $inputs  各worker的原始請求
     * @param  ?\Closure  $whileRunning  釋放worker後的可選回呼，接收本次暫存檔前綴
     * @param  array<int,string>  $actors  各worker的合成帳號, 未指定時使用同一操作者
     * @return list<array<string,mixed>> 各worker回應
     */
    private function race(array $identity, string $operation, array $inputs, ?\Closure $whileRunning = null, array $actors = []): array
    {
        $prefix = tempnam(sys_get_temp_dir(), 'tt-mysql-race-');
        $processes = [];
        try {
            foreach ($inputs as $i => $input) {
                file_put_contents($prefix.'.'.$i.'.json', json_encode(['userId' => $actors[$i] ?? $identity['user']['id'], 'workspaceId' => $identity['workspace']['id'], 'operation' => $operation, 'input' => $input, 'ready' => $prefix.'.'.$i.'.ready', 'start' => $prefix.'.start', 'result' => $prefix.'.'.$i.'.result'], JSON_THROW_ON_ERROR));
                $process = new Process([PHP_BINARY, base_path('tools/mysql-race-worker.php'), $prefix.'.'.$i.'.json']);
                $process->setTimeout(15)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 8;
            foreach (array_keys($inputs) as $i) {
                while (! is_file($prefix.'.'.$i.'.ready')) {
                    if (microtime(true) > $deadline) {
                        $this->fail('worker未就緒：'.$processes[$i]->getErrorOutput());
                    }
                    usleep(10000);
                }
            }
            touch($prefix.'.start');
            if ($whileRunning) {
                $whileRunning($prefix);
            }
            $results = [];
            foreach ($processes as $i => $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = json_decode(file_get_contents($prefix.'.'.$i.'.result'), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($prefix.'*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * 驗證兩個獨立MySQL寫入程序競爭時，每人每日工時仍不超過24小時。
     *
     * @return void
     */
    public function test_parallel_time_entries_cannot_exceed_daily_limit(): void
    {
        [$identity, , $issue] = $this->fixture();
        $inputs = array_map(fn () => ['issueId' => $issue['id'], 'workDate' => now('Asia/Taipei')->toDateString(), 'minutes' => 800, 'requestId' => (string) Str::uuid()], [1, 2]);
        $results = $this->race($identity, 'logTime', $inputs);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertSame(['INVALID_INPUT'], array_values(array_column(array_filter($results, fn ($r) => ! $r['ok']), 'code')));
    }

    /**
     * 驗證並行改同版本只成功一次，同requestId建立請求只產生一張卡片。
     *
     * @return void
     */
    public function test_parallel_revision_conflict_and_replay_create_once(): void
    {
        [$identity, $project, $issue] = $this->fixture();
        $results = $this->race($identity, 'updateIssue', array_map(fn ($title) => ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'title' => $title, 'requestId' => (string) Str::uuid()], ['one', 'two']));
        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']));
        $this->assertSame(['REVISION_CONFLICT'], array_values(array_column(array_filter($results, fn ($r) => ! $r['ok']), 'code')));
        $input = ['projectId' => $project['id'], 'title' => 'exactly once', 'requestId' => (string) Str::uuid()];
        $results = $this->race($identity, 'createIssue', [$input, $input]);
        $this->assertTrue($results[0]['ok']);
        $this->assertDtoSame($results[0], $results[1]);
        $this->assertSame(1, DB::table('issues')->where('public_id', $results[0]['data']['id'])->count());
    }

    /**
     * 驗證等待Workspace鎖的寫入者取得鎖後重查撤權結果，不提交失效寫入。
     *
     * @return void
     */
    public function test_waiting_writer_rechecks_revoked_permissions_after_workspace_lock(): void
    {
        [$owner, $project, $issue] = $this->fixture();
        $email = Str::uuid().'@example.test';
        $invite = $this->postJson('/api/rpc/inviteMember', ['requestId' => (string) Str::uuid(), 'email' => $email])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        $developer = $this->postJson('/api/auth/register', ['name' => 'developer', 'email' => $email, 'password' => 'mysql-race-password', 'inviteToken' => $invite['token']])->assertOk()->json('data');
        $this->actingAs(User::findOrFail($owner['user']['id']))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $acl = $this->postJson('/api/rpc/getProjectMembers', ['requestId' => (string) Str::uuid(), 'projectId' => $project['id']])->assertOk()->json('data');
        $acl = $this->postJson('/api/rpc/setProjectMember', ['requestId' => (string) Str::uuid(), 'projectId' => $project['id'], 'userId' => $developer['user']['id'], 'roleId' => 'developer', 'expectedRevision' => $acl['revision']])->assertOk()->json('data');
        DB::beginTransaction();
        try {
            (new WriteLocks)->workspace($owner['workspace']['id']);
            $results = $this->race($developer, 'updateIssue', [['requestId' => (string) Str::uuid(), 'id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'title' => '不得保存']], function ($prefix) use ($owner, $developer, $project, $acl) {
                usleep(150000);
                $this->assertFileDoesNotExist($prefix.'.0.result', 'writer必須等workspace鎖');
                $context = app(AccountService::class)->context(User::findOrFail($owner['user']['id']), $owner['workspace']['id']);
                app(WorkspaceService::class)->execute($context, 'removeProjectMember', ['requestId' => (string) Str::uuid(), 'projectId' => $project['id'], 'userId' => $developer['user']['id'], 'expectedRevision' => $acl['revision']]);
                DB::commit();
            });
            $this->assertFalse($results[0]['ok']);
            $this->assertContains($results[0]['code'], ['FORBIDDEN', 'NOT_FOUND']);
            $this->assertSame('race', DB::table('issues')->where('public_id', $issue['id'])->value('title'));
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
        }
    }

    /** 兩個獨立帳號競爭最後一個名額, 接受邀請與恢復皆只能成功一次
     * @return void
     */
    public function test_parallel_membership_acceptance_and_restoration_respect_capacity(): void
    {
        foreach (['acceptWorkspaceInvite', 'setWorkspaceMemberStatus'] as $operation) {
            [$owner] = $this->fixture();
            $workspace = Workspace::where('public_id', $owner['workspace']['id'])->value('id');
            $inputs = $actors = [];
            for ($i = 0; $i < 5; $i++) {
                $user = User::create(['id' => (string) Str::uuid(), 'name' => 'Capacity racer', 'email' => Str::uuid().'@example.test', 'password' => 'capacity-race-password']);
                if ($i < 3 || $operation === 'setWorkspaceMemberStatus') {
                    $member = WorkspaceMember::create(['workspace_id' => $workspace, 'user_id' => $user->id, 'role' => 'user', 'joined_at' => now(), 'disabled_at' => $i < 3 ? null : now()]);
                }
                if ($i < 3) {
                    continue;
                }
                if ($operation === 'acceptWorkspaceInvite') {
                    $invite = $this->postJson('/api/rpc/inviteMember', ['email' => $user->email, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
                    $inputs[] = ['inviteToken' => $invite['token']];
                    $actors[] = $user->id;
                } else {
                    $inputs[] = ['userId' => $user->id, 'status' => 'active', 'expectedRevision' => (int) $member->role_revision, 'requestId' => (string) Str::uuid()];
                }
            }
            $results = $this->race($owner, $operation, $inputs, actors: $actors);
            $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']), $operation.': '.json_encode($results));
            $this->assertSame(['CONFLICT'], array_values(array_column(array_filter($results, fn ($result) => ! $result['ok']), 'code')));
            $this->assertSame(5, WorkspaceMember::where('workspace_id', $workspace)->whereNull('disabled_at')->count());
        }
    }
}
