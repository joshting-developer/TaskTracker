<?php

namespace Tests\Concerns;

use App\Http\Requests\Writes\ProjectWriteRequest;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** 非卡片／工時寫入的型別、固定結果、原始簽章與歷史receipt相容驗收。 */
trait OtherWriteBoundaryCases
{
    /**
     * 建立本測試專用帳號，不使用真實資料。
     *
     * @return array 帳號及空間身分
     */
    private function actor(): array
    {
        return $this->postJson('/api/auth/register', ['name' => 'Boundary', 'email' => 'boundary@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Boundary'])->assertOk()->json('data');
    }

    /**
     * 呼叫真實Session RPC，回應保持原樣而不自動補GET掩蓋契約。
     *
     * @param  string  $operation  固定操作
     * @param  array  $input  原始輸入
     * @return TestResponse HTTP回應
     */
    private function rpc(string $operation, array $input): TestResponse
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 每個非Issue／Time寫入都經專用Request，不能由通用RPC繞過；各領域錯型別保留DomainError。
     *
     * @return void
     */
    public function test_route_matrix_and_each_domain_type_boundary(): void
    {
        $domains = [
            'Project' => ['createProject', 'updateProject', 'updateStatuses'],
            'Board' => ['createBoard', 'updateBoard', 'archiveBoard'],
            'Todo' => ['createTodo', 'updateTodo', 'completeTodo', 'reopenTodo', 'returnTodo', 'cancelTodo', 'convertTodo', 'readTodoNotification'],
            'ProjectMember' => ['setProjectMember', 'removeProjectMember'],
            'Workspace' => ['inviteMember', 'loadDemo'],
            'Settings' => ['getProfile', 'updateProfile', 'updateWorkspace', 'setWorkspaceMemberRole'],
            'Account' => ['register', 'login', 'workspaces', 'switchWorkspace', 'acceptWorkspaceInvite', 'invitation'],
        ];
        foreach ($domains as $domain => $operations) {
            foreach ($operations as $operation) {
                $path = '/api/'.($domain === 'Account' ? 'auth' : 'rpc').'/'.$operation;
                $route = Route::getRoutes()->match(Request::create($path, 'POST'));
                $method = new \ReflectionMethod($route->getControllerClass(), $route->getActionMethod());
                $this->assertSame('App\\Http\\Requests\\Writes\\'.$domain.'WriteRequest', $method->getParameters()[0]->getType()->getName(), $path);
            }
        }
        $this->actor();
        foreach (['createBoard' => ['selectedIssueIds' => 'invalid'], 'createTodo' => ['content' => []], 'inviteMember' => ['email' => []], 'updateWorkspace' => ['name' => []], 'setProjectMember' => ['userId' => []], 'convertTodo' => ['issue' => ['projectId' => []]]] as $operation => $input) {
            $this->rpc($operation, $input)->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        }
        $this->postJson('/api/auth/login', ['email' => [], 'password' => 'test-local-password'])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    /**
     * 明確欄位有型別，未知／null／缺漏及空白保持原始簽章；無效JSON及型別返回DomainError。
     *
     * @return void
     */
    public function test_typed_data_preserves_raw_input_and_http_signature(): void
    {
        $actor = $this->actor();
        $input = ['name' => '  Example  ', 'code' => 'RAW', 'description' => null, 'requestId' => 'raw-project', 'extra' => ['nested' => null, 'list' => [2, 1]], 'operation' => 'updateWorkspace'];
        $request = ProjectWriteRequest::create('/api/rpc/createProject', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($input));
        $data = $request->toDto();
        $this->assertSame('createProject', $data->operation);
        $this->assertSame('  Example  ', $data->name);
        $this->assertSame($input, $data->input());
        $this->assertArrayNotHasKey('leadId', $data->input());
        $first = $this->rpc('createProject', $input)->assertOk()->json('data');
        $this->assertSame(['id', 'revision'], array_keys($first));
        $this->assertSame($first, $this->rpc('createProject', $input)->assertOk()->json('data'));
        $this->rpc('createProject', [...$input, 'extra' => ['nested' => true]])->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_CONFLICT');
        $this->getJson('/api/v1/projects/'.$first['id'])->assertOk()->assertJsonPath('data.name', 'Example')->assertJsonPath('data.description', '');
        foreach ([['name' => []], ['expectedRevision' => []], ['statuses' => 'invalid']] as $invalid) {
            $this->rpc('updateProject', ['id' => $first['id'], ...$invalid])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        }
        $this->rpc('updateProject', ['id' => 'invisible', 'expectedRevision' => 1])->assertStatus(404);
        $this->rpc('updateBoard', ['id' => 'invisible', 'expectedRevision' => 1])->assertStatus(404);
        $this->rpc('setWorkspaceMemberRole', ['userId' => 'absent', 'roleId' => 'user', 'expectedRevision' => 1])->assertStatus(404);
    }

    /**
     * Project歷史和Board收錄不進新receipt；GET才讀目前資料，舊receipt仍回原結果並先重驗權限。
     *
     * @return void
     */
    public function test_fixed_results_old_receipts_and_revocation_remain_separate(): void
    {
        $actor = $this->actor();
        $create = ['name' => 'Long project', 'code' => 'LONG', 'description' => str_repeat('說明', 100), 'requestId' => 'fixed-project'];
        $first = $this->rpc('createProject', $create)->assertOk()->json('data');
        $id = $first['id'];
        $issueIds = [];
        for ($n = 0; $n < 12; $n++) {
            $issueIds[] = $this->rpc('createIssue', ['projectId' => $id, 'title' => 'Card '.$n])->assertOk()->json('data.id');
        }
        $board = $this->rpc('createBoard', ['projectId' => $id, 'name' => 'Selected', 'selectedIssueIds' => $issueIds])->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['id', 'projectId', 'revision'], array_keys($board));
        $this->getJson('/api/v1/projects/'.$id.'/boards/'.$board['id'])->assertOk()->assertJsonCount(12, 'data.selectedIssueIds');
        for ($n = 1; $n <= 8; $n++) {
            $result = $this->rpc('updateProject', ['id' => $id, 'name' => 'Name '.$n, 'expectedRevision' => $n])->assertOk()->json('data');
            $this->assertSame(['id' => $id, 'revision' => $n + 1], $result);
        }
        $this->getJson('/api/v1/projects/'.$id)->assertOk()->assertJsonPath('data.name', 'Name 8')->assertJsonMissingPath('data.detailsHistory');
        $raw = DB::table('operation_receipts')->where('public_id', $actor['user']['id'].':fixed-project')->value('result');
        $this->assertLessThan(120, strlen($raw));
        $this->assertSame($first, json_decode($raw, true));
        $legacy = [...$first, 'name' => 'Original legacy result', 'detailsHistory' => [['changes' => ['large' => str_repeat('history', 100)]]]];
        DB::table('operation_receipts')->where('public_id', $actor['user']['id'].':fixed-project')->update(['result' => json_encode($legacy)]);
        $this->assertJsonStringEqualsJsonString(json_encode($legacy), json_encode($this->rpc('createProject', $create)->assertOk()->json('data')));
        $this->getJson('/api/v1/projects/'.$id)->assertOk()->assertJsonPath('data.name', 'Name 8');
        $archived = $this->rpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => 1])->assertOk()->json('data');
        $this->assertSame(2, $archived['revision']);
        WorkspaceMember::where('user_id',$actor['user']['id'])->update(['role' => 'user']);
        $this->rpc('createProject',$create)->assertStatus(403)->assertJsonPath('error.code','FORBIDDEN');
    }
}
