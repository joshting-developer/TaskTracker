<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** TT-428：看板手動排序以看板為範圍保存, 不受卡片更新影響, 並受權限與收錄限制 */
class BoardPositionTest extends TestCase
{
    use RefreshDatabase;

    private array $owner;

    private array $project;

    /** @var list<array<string,mixed>> */
    private array $issues = [];

    /** 建立擁有者、專案與三張卡片
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'order-owner@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Order'])->assertOk()->json('data');
        $this->project = $this->rpc('createProject', ['name' => 'Order', 'code' => 'ORD'])->assertOk()->json('data');
        foreach (['First', 'Second', 'Third'] as $title) {
            $this->issues[] = $this->rpc('createIssue', ['projectId' => $this->project['id'], 'title' => $title])->assertOk()->json('data');
        }
    }

    /** 送出真實 RPC, 每次帶新的 requestId
     * @param  string  $op  已註冊的 RPC 操作
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse 測試回應
     */
    private function rpc(string $op, array $input = []): TestResponse
    {
        return $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /** 讀回看板卡片的 id 對位置
     * @param  string  $board  看板公開 ID 或 default
     * @return array<string,int|null> 位置
     */
    private function positions(string $board = 'default'): array
    {
        $items = $this->getJson('/api/v1/projects/'.$this->project['id'].'/boards/'.$board.'/issues?perPage=100')->assertOk()->json('data.items');

        return array_column($items, 'boardPosition', 'id');
    }

    /** 依位置排出卡片標題, 沒排過的放後面
     * @param  string  $board  看板公開 ID 或 default
     * @return list<string> 由上到下的 id
     */
    private function order(string $board = 'default'): array
    {
        $positions = array_filter($this->positions($board), fn ($p) => $p !== null);
        asort($positions);

        return array_keys($positions);
    }

    /** 排序保存後重讀不變, 卡片更新不洗掉順序, 版本會改變, 同 requestId 重送不重複寫入
     * @return void
     */
    public function test_manual_order_persists_and_survives_card_updates(): void
    {
        [$first, $second, $third] = array_column($this->issues, 'id');
        $this->assertEquals([$first => null, $second => null, $third => null], array_intersect_key($this->positions(), [$first => 1, $second => 1, $third => 1]), '還沒排過的卡片明確回 null');
        $path = '/api/v1/projects/'.$this->project['id'].'/boards/default/version';
        $before = $this->getJson($path)->assertOk()->json('data.version');

        $request = ['projectId' => $this->project['id'], 'boardId' => 'default', 'issueIds' => [$third, $first, $second], 'requestId' => (string) Str::uuid()];
        $result = $this->postJson('/api/rpc/reorderBoardCards', $request)->assertOk()->json('data');
        $this->assertSame([['id' => $third, 'position' => 1024], ['id' => $first, 'position' => 2048], ['id' => $second, 'position' => 3072]], $result['positions']);
        $this->assertSame([$third, $first, $second], $this->order());
        $afterOrder = $this->getJson($path)->assertOk()->json('data.version');
        $this->assertNotSame($before, $afterOrder, '排序改變看板版本, 其他人輪詢會重讀');

        $this->postJson('/api/rpc/reorderBoardCards', $request)->assertOk();
        $this->assertSame(3, (int) DB::table('board_card_positions')->sum('revision'), '同 requestId 重送回第一次結果, 不再寫入');

        // 互換兩張卡, 位置加總不變但版本仍要改變
        $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => 'default', 'issueIds' => [$first, $third, $second]])->assertOk();
        $this->assertNotSame($afterOrder, $this->getJson($path)->assertOk()->json('data.version'));

        $issue = $this->rpc('updateIssue', ['id' => $second, 'title' => 'Second renamed', 'priority' => 'urgent', 'expectedRevision' => $this->issues[1]['revision']])->assertOk()->json('data');
        $this->rpc('updateIssue', ['id' => $second, 'state' => 'inprogress', 'expectedRevision' => $issue['revision']])->assertOk();
        $this->assertSame([$first, $third, $second], $this->order(), '改標題、優先級或狀態都不改看板順序');
        $this->assertSame($this->issues[1]['revision'] + 2, (int) DB::table('issues')->where('public_id', $second)->value('revision'), '排序不動卡片 revision, 只有兩次編輯');
    }

    /** 不同看板各自排序, 不在看板內的卡片、封存看板與跨專案卡片都拒絕
     * @return void
     */
    public function test_order_is_scoped_per_board_and_limited_to_board_cards(): void
    {
        [$first, $second, $third] = array_column($this->issues, 'id');
        $board = $this->rpc('createBoard', ['projectId' => $this->project['id'], 'name' => 'Custom', 'selectedIssueIds' => [$first, $second]])->assertOk()->json('data');
        $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => 'default', 'issueIds' => [$first, $second, $third]])->assertOk();
        $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => $board['id'], 'issueIds' => [$second, $first]])->assertOk();
        $this->assertSame([$first, $second, $third], $this->order());
        $this->assertSame([$second, $first], $this->order($board['id']));

        $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => $board['id'], 'issueIds' => [$third, $first]])
            ->assertJsonPath('error.code', 'DATA_CHANGED');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTH'])->assertOk()->json('data');
        $outside = $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Outside'])->assertOk()->json('data');
        $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => 'default', 'issueIds' => [$outside['id'], $first]])
            ->assertJsonPath('error.code', 'DATA_CHANGED');
        foreach ([[], [$first, $first], array_fill(0, 501, $first)] as $ids) {
            $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => 'default', 'issueIds' => $ids])->assertJsonPath('error.code', 'INVALID_INPUT');
        }
        $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => 'default', 'issueIds' => [$first], 'workspaceId' => 'other'])->assertJsonPath('error.code', 'INVALID_INPUT');

        $current = $this->getJson('/api/v1/projects/'.$this->project['id'].'/boards/'.$board['id'])->assertOk()->json('data');
        $this->rpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => $current['revision']])->assertOk();
        $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => $board['id'], 'issueIds' => [$first]])->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /** 唯讀成員與未加入專案的成員不能排序, 也不能藉錯誤訊息探測卡片
     * @return void
     */
    public function test_readers_and_outsiders_cannot_reorder(): void
    {
        $tenant = (new RecordStore)->forWorkspace($this->owner['workspace']['id']);
        foreach (['reader', 'outsider'] as $name) {
            $user = User::create(['id' => (string) Str::uuid(), 'name' => $name, 'email' => $name.'@example.test', 'password' => Hash::make('test-local-password')]);
            $tenant->put('members', $user->id, ['id' => $user->id, 'name' => $name, 'email' => $user->email, 'role' => 'user']);
            if ($name === 'reader') {
                $revision = $this->rpc('getProjectMembers', ['projectId' => $this->project['id']])->assertOk()->json('data.revision');
                $this->rpc('setProjectMember', ['projectId' => $this->project['id'], 'userId' => $user->id, 'roleId' => 'reader', 'expectedRevision' => $revision])->assertOk();
            }
        }
        foreach (['reader' => 'FORBIDDEN', 'outsider' => ['FORBIDDEN', 'NOT_FOUND']] as $name => $codes) {
            Auth::logout();
            $this->postJson('/api/auth/login', ['email' => $name.'@example.test', 'password' => 'test-local-password'])->assertOk();
            $code = $this->rpc('reorderBoardCards', ['projectId' => $this->project['id'], 'boardId' => 'default', 'issueIds' => [$this->issues[0]['id']]])->json('error.code');
            $this->assertContains($code, (array) $codes, $name);
        }
        $this->assertSame(0, DB::table('board_card_positions')->count());
    }
}
