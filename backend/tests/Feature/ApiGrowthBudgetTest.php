<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** TT-226：一般頁面讀取端點的SQL次數與回應大小不隨卡片、成員、工時與留言成長。 */
class ApiGrowthBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 透過正式HTTP寫入建立人造測試資料。
     *
     * @param  string  $operation  RPC名稱
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse HTTP測試回應
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 量測每個端點的SQL次數與回應bytes。
     *
     * @param  array<string,string>  $paths  名稱→路徑
     * @return array<string,array{queries:int,bytes:int}> 量測結果
     */
    private function measure(array $paths): array
    {
        $result = [];
        foreach ($paths as $name => $path) {
            DB::flushQueryLog();
            $response = $this->getJson($path)->assertOk();
            $result[$name] = ['queries' => count(DB::getQueryLog()), 'bytes' => strlen($response->getContent())];
        }

        return $result;
    }

    /**
     * 小資料與成長後（+150卡、+40成員、+120筆跨期工時、+30則留言）比較；分頁端點以perPage為上限。
     *
     * @return void
     */
    public function test_page_reads_do_not_grow_with_workspace_data(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Growth Owner', 'email' => 'growth-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Growth'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Growth', 'code' => 'GRW'])->assertOk()->json('data');
        $epic = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Epic', 'type' => 'epic'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Seed', 'epicId' => $epic['id'], 'assignee' => $identity['user']['id']])->assertOk()->json('data');
        $makeIssues = function (int $count) use ($project, $epic, $identity) {
            for ($n = 0; $n < $count; $n++) {
                $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Card '.$n, 'description' => str_repeat('d', 200), 'epicId' => $n % 2 ? $epic['id'] : '', 'assignee' => $n % 3 ? $identity['user']['id'] : ''])->assertOk();
            }
        };
        $makeIssues(25);
        $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => '2026-10-05', 'minutes' => 30, 'note' => 'n'])->assertOk();
        // 基準即含一則留言：空結果會略過關聯查詢，不能當成逐筆成長。
        $this->rpc('addComment', ['id' => $issue['id'], 'content' => 'baseline', 'expectedRevision' => $issue['revision']])->assertOk();
        $p = '/api/v1/projects/'.$project['id'];
        $paths = [
            'project' => $p, 'projects' => '/api/v1/projects?page=1&perPage=20',
            'list' => $p.'/issues?page=1&perPage=20', 'detail' => $p.'/issues/'.$issue['id'], 'comments' => $p.'/issues/'.$issue['id'].'/comments?page=1&perPage=20',
            'history' => $p.'/issues/'.$issue['id'].'/history?page=1&perPage=20', 'boardMeta' => $p.'/boards/default', 'boardPage' => $p.'/boards/default/issues?page=1&perPage=20',
            'boardVersion' => $p.'/boards/default/version', 'memberOptions' => $p.'/member-options?page=1&perPage=20', 'issueOptions' => $p.'/issue-options?page=1&perPage=20',
            'calendar' => $p.'/time-calendar?month=2026-10', 'day' => $p.'/time-entries?date=2026-10-05&page=1&perPage=20', 'acl' => $p.'/acl/members?page=1&perPage=20',
            'personalVersion' => '/api/v1/personal-version', 'todos' => '/api/v1/todos?page=1&perPage=20',
        ];
        DB::enableQueryLog();
        $before = $this->measure($paths);
        DB::disableQueryLog();

        $makeIssues(150);
        $store = (new RecordStore)->forWorkspace($identity['workspace']['id']);
        $acl = $this->getJson($p.'/acl')->assertOk()->json('data.revision');
        for ($n = 0; $n < 40; $n++) {
            $user = User::create(['id' => (string) Str::uuid(), 'name' => 'Member '.$n, 'email' => 'm'.$n.'-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
            $store->put('members', $user->id, ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'user']);
            $acl = $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $user->id, 'roleId' => 'developer', 'expectedRevision' => $acl])->assertOk()->json('data.revision');
        }
        for ($n = 0; $n < 120; $n++) {
            $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => sprintf('2026-%02d-%02d', 1 + $n % 10, 1 + $n % 9), 'minutes' => 5, 'note' => 'growth'])->assertOk();
        }
        $revision = $this->getJson($p.'/issues/'.$issue['id'])->assertOk()->json('data.revision');
        for ($n = 0; $n < 30; $n++) {
            $revision = $this->rpc('addComment', ['id' => $issue['id'], 'content' => 'comment '.$n, 'expectedRevision' => $revision])->assertOk()->json('data.revision');
        }
        DB::enableQueryLog();
        $after = $this->measure($paths);

        foreach ($paths as $name => $path) {
            $this->assertLessThanOrEqual($before[$name]['queries'], $after[$name]['queries'], "{$name} SQL次數不隨資料成長（{$before[$name]['queries']}→{$after[$name]['queries']}）");
        }
        // 分頁／聚合端點回應受perPage或固定欄位限制；只依資料量成長的端點必須是分頁。
        foreach (['project', 'detail', 'boardMeta', 'boardVersion', 'personalVersion'] as $name) {
            $this->assertLessThanOrEqual($before[$name]['bytes'] + 400, $after[$name]['bytes'], "{$name} 回應不隨資料成長");
        }
        foreach (['list', 'boardPage', 'comments', 'history', 'memberOptions', 'issueOptions', 'day', 'acl', 'todos', 'projects'] as $name) {
            $this->assertLessThanOrEqual(20000, $after[$name]['bytes'], "{$name} 分頁回應有上限");
        }
        $this->assertLessThanOrEqual(8000, $after['calendar']['bytes'], '月曆只回聚合，不含原始工時');
        fwrite(STDERR, 'growth budgets '.json_encode(['before' => $before, 'after' => $after], JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}
