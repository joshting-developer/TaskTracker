<?php

namespace Tests\Concerns;

use App\Domain\Fields;
use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 看板輕量變更檢查在memory與獨立MySQL共用的語意與授權斷言。 */
trait BoardVersionCases
{
    /**
     * 版本只在看板畫面相依資料變更時改變；不含內容、不洩漏不可見工時，撤權／跨Workspace／封存回錯誤。
     *
     * @return void
     */
    public function test_board_version_is_lightweight_reliable_and_scoped(): void
    {
        $email = 'version-'.Str::uuid().'@example.test';
        $identity = $this->postJson('/api/auth/register', ['name' => 'Version Owner', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => 'Version'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Version', 'code' => 'VER'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Elsewhere', 'code' => 'ELSE'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Secret card title', 'description' => 'secret body', 'assignee' => $identity['user']['id']])->assertOk()->json('data');
        $outside = $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Outside'])->assertOk()->json('data');
        $board = $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => 'Custom', 'selectedIssueIds' => [$issue['id']]])->assertOk()->json('data');
        $path = '/api/v1/projects/'.$project['id'].'/boards/';
        $version = fn (string $id = 'default') => $this->getJson($path.$id.'/version')->assertOk()->json('data.version');

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->getJson($path.'default/version')->assertOk();
        $this->assertSame(['projectId', 'boardId', 'version'], array_keys($response->json('data')));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $response->json('data.version'));
        $this->assertStringNotContainsString('Secret card title', $response->getContent(), '只回不透明版本');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), '私有版本不進共用快取');
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => preg_match('/description|title|note|audit_events|comments/', $q['query'])), '不讀卡片內容、工時說明或歷史');
        $queries = count(DB::getQueryLog());
        for ($index = 0; $index < 5; $index++) {
            $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Extra '.$index])->assertOk();
        }
        DB::flushQueryLog();
        $this->getJson($path.'default/version')->assertOk();
        $this->assertSame($queries, count(DB::getQueryLog()), '查詢次數不隨卡片數成長');

        $base = $version();
        $this->assertSame($base, $version(), '無變更時版本穩定');
        $custom = $version($board['id']);

        $this->rpc('updateIssue', ['projectId' => $other['id'], 'id' => $outside['id'], 'title' => 'Outside 2', 'expectedRevision' => $outside['revision']])->assertOk();
        $this->assertSame($base, $version(), '其他Project變更不影響');

        $issue = $this->rpc('updateIssue', ['id' => $issue['id'], 'state' => 'inprogress', 'expectedRevision' => $issue['revision']])->assertOk()->json('data');
        $afterMove = $version();
        $this->assertNotSame($base, $afterMove, '卡片移動改變版本');
        $this->assertNotSame($custom, $version($board['id']), '自訂看板同樣感知');

        $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => '2026-10-05', 'minutes' => 30, 'note' => 'private note'])->assertOk();
        $afterTime = $version();
        $this->assertNotSame($afterMove, $afterTime, '可見工時變更改變版本');

        DB::table('users')->where('id', $identity['user']['id'])->update(['name' => 'Renamed', 'profile_revision' => DB::raw('profile_revision + 1')]);
        $afterRename = $version();
        $this->assertNotSame($afterTime, $afterRename, '承辦者改名改變版本');

        $states = [...Fields::STATES, ['id' => 'accepted', 'label' => '驗收', 'color' => '#123456']];
        $this->rpc('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => $project['revision'], 'statuses' => $states])->assertOk();
        $afterStatuses = $version();
        $this->assertNotSame($afterRename, $afterStatuses, 'Project狀態設定改變版本');

        $customBefore = $version($board['id']);
        $board = $this->getJson($path.$board['id'])->assertOk()->json('data');
        $this->rpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'], 'name' => 'Custom 2'])->assertOk();
        $this->assertNotSame($customBefore, $version($board['id']), '看板屬性變更改變版本');
        $this->assertSame($afterStatuses, $version(), 'Default不受自訂看板影響');

        // 讀者沒有工時權限：他人工時變更不改讀者版本；撤權後不能再探測。
        $reader = User::create(['id' => (string) Str::uuid(), 'name' => 'Reader', 'email' => 'reader-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        (new RecordStore)->forWorkspace($identity['workspace']['id'])->put('members', $reader->id, ['id' => $reader->id, 'name' => $reader->name, 'email' => $reader->email, 'role' => 'user']);
        $acl = $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'roleId' => 'reader', 'expectedRevision' => 0])->assertOk()->json('data');
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $reader->email, 'password' => 'test-local-password'])->assertOk();
        $readerBase = $version();
        $this->assertNotSame($afterStatuses, $readerBase, '有效權限不同時版本不同');
        $this->getJson('/api/v1/projects/'.$other['id'].'/boards/default/version')->assertStatus(404);
        $login = function (string $who) {
            Auth::logout();
            $this->postJson('/api/auth/login', ['email' => $who, 'password' => 'test-local-password'])->assertOk();
        };
        $login($email);
        $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => '2026-10-06', 'minutes' => 15, 'note' => 'owner only'])->assertOk();
        $this->assertNotSame($afterStatuses, $version(), '擁有者看得到新工時');
        $login($reader->email);
        $this->assertSame($readerBase, $version(), '讀者看不到工時，版本不洩漏他人投入');

        $login($email);
        $board = $this->getJson($path.$board['id'])->assertOk()->json('data');
        $this->rpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision']])->assertOk();
        // TT-283：封存看板唯讀可查, 版本也讀得到。
        $this->getJson($path.$board['id'].'/version')->assertOk();
        $this->rpc('removeProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'expectedRevision' => $acl['revision']])->assertOk();
        $login($reader->email);
        $this->assertContains($this->getJson($path.'default/version')->status(), [403, 404], '撤權後不能探測版本');

        Auth::logout();
        $this->postJson('/api/auth/register', ['name' => 'Stranger', 'email' => 'stranger-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Other'])->assertOk();
        $this->getJson($path.'default/version')->assertStatus(404);
    }
}
