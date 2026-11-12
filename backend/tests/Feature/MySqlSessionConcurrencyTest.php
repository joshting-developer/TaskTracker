<?php

namespace Tests\Feature;

use App\Domain\DomainError;
use App\Factories\SessionLockFactory;
use App\Http\WorkspaceTransaction;
use App\Http\Session\SessionLease;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\MySqlTestCase;

/** 真實四worker、共用DB Session與獨立CookieJar，驗證超過10秒時的Session順序。 */
class MySqlSessionConcurrencyTest extends MySqlTestCase
{
    private string $url;

    /** @return Client 獨立Cookie與CSRF的測試客戶端 */
    private function client(): Client
    {
        return new Client(['base_uri' => $this->url, 'cookies' => new CookieJar, 'timeout' => 35, 'http_errors' => false, 'headers' => ['Accept' => 'application/json', 'Origin' => 'http://127.0.0.1:5175']]);
    }

    /**
     * 以真實Cookie／CSRF執行測試API，不繞過Session中介層。
     *
     * @param  Client  $client  獨立客戶端
     * @param  string  $path  API路徑
     * @param  array  $body  JSON輸入
     * @param  int  $status  期望狀態碼
     * @return array 業務data
     */
    private function api(Client $client, string $path, array $body = [], int $status = 200): array
    {
        $csrf = json_decode($client->get('/api/csrf-cookie')->getBody(), true)['csrfToken'];
        $response = $client->post($path, ['headers' => ['X-CSRF-TOKEN' => $csrf], 'json' => ['requestId' => (string) Str::uuid(), ...$body]]);
        $data = json_decode($response->getBody(), true);
        $this->assertSame($status, $response->getStatusCode(), json_encode($data));

        return $data['data'] ?? $data;
    }

    /** @return void */
    public function test_slow_request_switch_and_revoke_never_overwrite_session_or_write_after_revoke(): void
    {
        $prefix = tempnam(sys_get_temp_dir(), 'tt-session-');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->url = 'http://'.$address;
        $server = new Process(['setsid', PHP_BINARY, '-S', $address, '-t', public_path(), base_path('tools/session-race-router.php')], base_path(), ['PHP_CLI_SERVER_WORKERS' => '4', 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'file', 'TASKTRACKER_SESSION_RACE_PREFIX' => $prefix]);
        $server->setTimeout(70)->start();
        $worker = null;
        try {
            $deadline = microtime(true) + 6;
            while (@file_get_contents($this->url.'/api/health') === false) {
                if (microtime(true) > $deadline) {
                    $this->fail('HTTP worker未就緒');
                }
                usleep(50000);
            }
            $owner = $this->client();
            $identity = $this->api($owner, '/api/auth/register', ['name' => 'owner', 'email' => Str::uuid().'@example.test', 'password' => 'session-test-password', 'workspaceName' => 'A']);
            $project = $this->api($owner, '/api/rpc/createProject', ['name' => 'A', 'code' => 'ASESSION']);
            $issue = $this->api($owner, '/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'original']);
            $invite = $this->api($owner, '/api/rpc/inviteMember', ['email' => Str::uuid().'@example.test']);
            $actor = $this->client();
            $actorIdentity = $this->api($actor, '/api/auth/register', ['name' => 'actor', 'email' => $invite['email'], 'password' => 'session-test-password', 'inviteToken' => $invite['token']]);
            $acl = $this->api($owner, '/api/rpc/getProjectMembers', ['projectId' => $project['id']]);
            $this->api($owner, '/api/rpc/setProjectMember', ['projectId' => $project['id'], 'userId' => $actorIdentity['user']['id'], 'roleId' => 'maintainer', 'expectedRevision' => $acl['revision']]);
            $other = $this->client();
            $second = $this->api($other, '/api/auth/register', ['name' => 'other', 'email' => Str::uuid().'@example.test', 'password' => 'session-test-password', 'workspaceName' => 'B']);
            $invitation = $this->api($other, '/api/rpc/inviteMember', ['email' => $invite['email']]);
            $this->api($actor, '/api/auth/acceptWorkspaceInvite', ['inviteToken' => $invitation['token']]);
            $this->api($actor, '/api/auth/switchWorkspace', ['workspaceId' => $identity['workspace']['id']]);

            $worker = $this->slow($actor, $prefix, $identity['workspace']['id'], ['mode' => 'read']);
            if (getenv('TASKTRACKER_SESSION_BASELINE') !== '1') {
                $headers = json_decode(file_get_contents($prefix.'.json'), true)['headers'];
                $busy = $actor->post('/api/test/session-wait', ['headers' => $headers, 'json' => []]);
                $this->assertSame(409, $busy->getStatusCode());
                $this->assertSame('CONFLICT', json_decode($busy->getBody(), true)['error']['code']);
                $this->assertFileDoesNotExist($prefix.'.unexpected', '取得鎖失敗不能進入業務路由');
            }
            usleep(10500000);
            $started = microtime(true);
            $this->api($actor, '/api/auth/switchWorkspace', ['workspaceId' => $second['workspace']['id']]);
            $wait = microtime(true) - $started;
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $slow = json_decode(file_get_contents($prefix.'.result'), true);
            $this->assertSame(200, $slow['status']);
            $current = $this->api($actor, '/api/auth/current');
            $baseline = getenv('TASKTRACKER_SESSION_BASELINE') === '1';
            $this->assertSame(($baseline ? $identity : $second)['workspace']['id'], $current['workspace']['id']);
            fwrite(STDERR, "\nSESSION_METRICS ".json_encode(['baseline' => $baseline, 'slowSeconds' => 12, 'switchWaitSeconds' => $wait, 'staleOverwrite' => $current['workspace']['id'] === $identity['workspace']['id']])."\n");
            if ($baseline) {
                return;
            }
            $this->assertGreaterThan(1, $wait, '超租期仍需等慢請求完成');
            $response = $actor->post('/api/rpc/updateIssue', ['headers' => ['X-CSRF-TOKEN' => json_decode($actor->get('/api/csrf-cookie')->getBody(), true)['csrfToken'], 'X-Workspace-ID' => $identity['workspace']['id']], 'json' => ['id' => $issue['id'], 'title' => 'cross-space', 'expectedRevision' => $issue['revision'], 'requestId' => (string) Str::uuid()]]);
            $this->assertSame(409, $response->getStatusCode());
            $this->api($actor, '/api/auth/switchWorkspace', ['workspaceId' => $identity['workspace']['id']]);
            unlink($prefix.'.ready');
            $worker = $this->slow($actor, $prefix, $identity['workspace']['id'], ['mode' => 'save', 'input' => ['id' => $issue['id'], 'title' => 'revoked-save', 'expectedRevision' => $issue['revision'], 'requestId' => (string) Str::uuid()]]);
            $acl = $this->api($owner, '/api/rpc/getProjectMembers', ['projectId' => $project['id']]);
            $this->api($owner, '/api/rpc/removeProjectMember', ['projectId' => $project['id'], 'userId' => $actorIdentity['user']['id'], 'expectedRevision' => $acl['revision']]);
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $denied = json_decode(file_get_contents($prefix.'.result'), true);
            $this->assertContains($denied['status'], [403, 404]);
            $saved = json_decode($owner->get('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'])->getBody(), true)['data'];
            $this->assertSame('original', $saved['title']);
            $this->assertSame($issue['revision'], $saved['revision']);
            // finally釋放鎖後同Session仍能繼續切換，不遺留永久鎖。
            $this->api($actor, '/api/auth/switchWorkspace', ['workspaceId' => $second['workspace']['id']]);
        } finally {
            if ($worker?->isRunning()) {
                $worker->stop();
            }
            if ($server->isRunning()) {
                posix_kill(-$server->getPid(), SIGTERM);
                $server->stop();
            }
            foreach (glob($prefix.'*') as $file) {
                unlink($file);
            }
        }
    }

    /** 程序死亡釋放鎖、短等待拒絕，以及交易內失鎖rollback。 @return void */
    public function test_connection_lock_releases_after_process_exit_and_lost_lock_rolls_back(): void
    {
        $prefix = tempnam(sys_get_temp_dir(), 'tt-session-lock-');
        $process = new Process([PHP_BINARY, base_path('tools/session-lock-worker.php'), $prefix]);
        $process->setTimeout(15)->start();
        try {
            $deadline = microtime(true) + 5;
            while (! is_file($prefix.'.ready')) {
                if (microtime(true) > $deadline || ! $process->isRunning()) {
                    $this->fail('鎖worker未就緒：'.$process->getErrorOutput());
                }
                usleep(10000);
            }
            $factory = app(SessionLockFactory::class);
            try {
                $factory->acquire('test', $prefix, 0);
                $this->fail('另一連線持鎖時不應成功');
            } catch (DomainError $error) {
                $this->assertSame('CONFLICT', $error->errorCode);
            }
            $process->stop();
            $lease = $factory->acquire('test', $prefix, 1);
            $lease->assertHeld();
            $lease->release();
            try {
                $lease->assertHeld();
                $this->fail('失鎖後不應繼續保存');
            } catch (DomainError $error) {
                $this->assertSame('CONFLICT', $error->errorCode);
            }
            $identity = $this->postJson('/api/auth/register', ['name' => 'lease', 'email' => Str::uuid().'@example.test', 'password' => 'lease-test-password', 'workspaceName' => 'lease'])->assertOk()->json('data');
            $request = Request::create('/api/test/lease');
            $request->setUserResolver(fn () => User::findOrFail($identity['user']['id']));
            $session = new Store('lease-test', new ArraySessionHandler(120));
            $session->start();
            $session->put('workspace_id', $identity['workspace']['id']);
            $request->setLaravelSession($session);
            $lease = $factory->acquire('test', $prefix, 1);
            $request->attributes->set(SessionLease::class, $lease);
            $marker = (string) Str::uuid();
            try {
                app(WorkspaceTransaction::class)->read($request, function () use ($lease, $marker) {
                    DB::table('workspaces')->insert(['public_id' => $marker, 'name' => 'rollback']);
                    $lease->release();

                    return [];
                });
                $this->fail('失鎖應使交易拒絕提交');
            } catch (DomainError $error) {
                $this->assertSame('CONFLICT', $error->errorCode);
            } finally {
                $lease->release();
            }
            $this->assertDatabaseMissing('workspaces', ['public_id' => $marker]);
        } finally {
            if ($process->isRunning()) {
                $process->stop();
            }
            foreach (glob($prefix.'*') as $file) {
                unlink($file);
            }
        }
    }

    /**
     * 啟動真實獨立HTTP客戶端並等到路由已開始，避免以sleep猜測就緒。
     *
     * @param  Client  $client  已登入客戶端
     * @param  string  $prefix  本測例暫存前綴
     * @param  string  $workspaceId  預期工作空間
     * @param  array  $body  固定測試路由輸入
     * @return Process 可等待的獨立客戶端
     */
    private function slow(Client $client, string $prefix, string $workspaceId, array $body): Process
    {
        $csrf = json_decode($client->get('/api/csrf-cookie')->getBody(), true)['csrfToken'];
        $cookies = implode('; ', array_map(fn ($c) => $c['Name'].'='.$c['Value'], $client->getConfig('cookies')->toArray()));
        file_put_contents($prefix.'.json', json_encode(['url' => $this->url.'/api/test/session-slow', 'body' => $body, 'result' => $prefix.'.result', 'headers' => ['Accept' => 'application/json', 'Origin' => 'http://127.0.0.1:5175', 'Cookie' => $cookies, 'X-CSRF-TOKEN' => $csrf, 'X-Workspace-ID' => $workspaceId]], JSON_THROW_ON_ERROR));
        chmod($prefix.'.json', 0600);
        $process = new Process([PHP_BINARY, base_path('tools/session-http-worker.php'), $prefix.'.json']);
        $process->setTimeout(35)->start();
        $deadline = microtime(true) + 8;
        while (! is_file($prefix.'.ready')) {
            if (microtime(true) > $deadline || ! $process->isRunning()) {
                $this->fail('慢請求未就緒：'.$process->getErrorOutput());
            }
            usleep(10000);
        }

        return $process;
    }
}
