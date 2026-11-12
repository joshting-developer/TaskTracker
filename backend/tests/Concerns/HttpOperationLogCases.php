<?php

namespace Tests\Concerns;

use App\Http\Middleware\RecordHttpOperation;
use App\Models\HttpOperationLog;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** TT-260：具名路由、回應後寫入、敏感欄位排除、失敗隔離、租戶與權限；SQLite與獨立MySQL共用。 */
trait HttpOperationLogCases
{
    /**
     * 經正式Session RPC送出並補requestId。
     *
     * @param  string  $operation  已註冊的RPC操作
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse 真實HTTP回應
     */
    private function logRpc(string $operation, array $input = []): TestResponse
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 註冊新帳號與工作區並回傳身分。
     *
     * @param  string  $label  帳號標記
     * @return array<string,mixed> 註冊回應data
     */
    private function logActor(string $label): array
    {
        return $this->postJson('/api/auth/register', ['name' => $label, 'email' => $label.'-'.Str::uuid().'@example.test', 'password' => 'oplog-secret-password', 'workspaceName' => $label])->assertOk()->json('data');
    }

    /**
     * 每條本專案API路由都有唯一名稱並掛上操作紀錄；框架內建/up、storage等非API路由不屬本專案操作。
     *
     * @return void
     */
    public function test_every_route_has_a_unique_name(): void
    {
        $names = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $this->assertNotNull($route->getName(), 'unnamed route: '.$route->uri());
            $names[] = $route->getName();
            $this->assertContains(RecordHttpOperation::class, $route->gatherMiddleware(), $route->uri());
        }
        $this->assertSame(count($names), count(array_unique($names)));
    }

    /**
     * Session寫入／失敗各一筆、讀取不記錄，欄位只含識別碼且不含密碼。
     *
     * @return void
     */
    public function test_session_writes_are_recorded_without_sensitive_content(): void
    {
        $actor = $this->logActor('oplog');
        $before = HttpOperationLog::count();
        $requestId = (string) Str::uuid();
        $project = $this->logRpc('createProject', ['name' => 'Log', 'code' => 'OPLOG', 'requestId' => $requestId])->assertOk()->json('data');
        $issue = $this->logRpc('createIssue', ['projectId' => $project['id'], 'title' => 'secret title text'])->assertOk()->json('data');
        $this->logRpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => 0, 'title' => 'stale'])->assertStatus(409);
        $this->logRpc('getWorkspace')->assertOk();
        $this->getJson('/api/v1/projects/'.$project['id'])->assertOk();

        $rows = HttpOperationLog::orderBy('id')->get()->slice($before)->values();
        $this->assertSame(['rpc.createProject', 'rpc.createIssue', 'rpc.updateIssue'], $rows->pluck('route_name')->all());
        $this->assertSame([200, 200, 409], $rows->pluck('status')->all());
        $this->assertSame($requestId, $rows[0]->request_id);
        $this->assertSame($project['id'], $rows[1]->project_public_id);
        foreach ($rows as $row) {
            $this->assertSame($actor['user']['id'], $row->user_id);
            $this->assertSame($actor['workspace']['id'], $row->workspace_public_id);
            $this->assertSame('POST', $row->method);
            $this->assertNull($row->token_id);
        }
        $all = json_encode(HttpOperationLog::all()->toArray());
        foreach (['oplog-secret-password', 'secret title text', 'stale'] as $secret) {
            $this->assertStringNotContainsString($secret, $all);
        }
        $this->assertSame([], array_diff(Schema::getColumnListing('http_operation_logs'), ['id', 'workspace_public_id', 'user_id', 'token_id', 'route_name', 'method', 'status', 'project_public_id', 'request_id', 'duration_ms', 'occurred_at']));
    }

    /**
     * 紀錄寫入失敗只寫log，不改變已成功的API回應與業務結果。
     *
     * @return void
     */
    public function test_log_failure_does_not_affect_response(): void
    {
        $this->logActor('broken');
        Schema::rename('http_operation_logs', 'http_operation_logs_off');
        try {
            $project = $this->logRpc('createProject', ['name' => 'Still', 'code' => 'STILL'])->assertOk()->json('data');
            $this->getJson('/api/v1/projects/'.$project['id'])->assertOk()->assertJsonPath('data.code', 'STILL');
        } finally {
            Schema::rename('http_operation_logs_off', 'http_operation_logs');
        }
    }

    /**
     * 讀取需workspace.operationLogs.read，只看目前Workspace；PAT寫入記Token ID；游標分頁有界。
     *
     * @return void
     */
    public function test_reading_logs_is_permission_and_workspace_scoped(): void
    {
        $other = $this->logActor('other');
        $this->logRpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();

        $actor = $this->logActor('owner');
        $project = $this->logRpc('createProject', ['name' => 'Mine', 'code' => 'MINE'])->assertOk()->json('data');
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'tool', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->json('POST', '/api/public/v1/projects/'.$project['id'].'/issues', ['title' => 'via token', 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer '.$token['plainTextToken']])->assertOk();
        $this->json('POST', '/api/public/v1/projects/'.$project['id'].'/issues', ['title' => 'bad token', 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer invalid'])->assertStatus(401);

        $items = $this->getJson('/api/v1/operation-logs?limit=100')->assertOk()->json('data.items');
        $this->assertNotEmpty($items);
        $this->assertSame([], array_values(array_filter($items, fn ($item) => $item['userId'] === $other['user']['id'])));
        $public = collect($items)->firstWhere('routeName', 'public.issues.create');
        $this->assertNotNull($public['tokenId']);
        $this->assertSame($actor['user']['id'], $public['userId']);
        $this->assertSame('owner', $public['userName']);
        $this->assertSame(1, HttpOperationLog::where('route_name', 'public.issues.create')->where('status', 401)->whereNull('workspace_public_id')->count());

        $first = $this->getJson('/api/v1/operation-logs?limit=1')->assertOk()->assertJsonCount(1, 'data.items')->json('data');
        $this->assertNotNull($first['nextBefore']);
        $second = $this->getJson('/api/v1/operation-logs?limit=1&before='.$first['nextBefore'])->assertOk()->json('data');
        $this->assertLessThan((int) $first['items'][0]['id'], (int) $second['items'][0]['id']);
        $this->getJson('/api/v1/operation-logs?limit=101')->assertStatus(400);
        $this->getJson('/api/v1/operation-logs?before=abc')->assertStatus(400);

        WorkspaceMember::where('user_id', $actor['user']['id'])->update(['role' => 'user']);
        $this->getJson('/api/v1/operation-logs')->assertStatus(403);
    }
}
