<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** TT-314：狀態調整通知負責人與建卡的人、重要狀態標示、去重與重送；SQLite與獨立MySQL共用。 */
trait IssueStatusNotificationCases
{
    /**
     * 在目前工作空間建立成員, 回傳登入用資料。
     *
     * @param  string  $workspaceId  工作空間ID
     * @param  string  $name  顯示名稱
     * @param  string  $role  工作空間角色
     * @return array{id: string, email: string}
     */
    private function statusMember(string $workspaceId, string $name, string $role = 'manager'): array
    {
        $user = User::create(['id' => (string) Str::uuid(), 'name' => $name, 'email' => strtolower($name).'-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        (new RecordStore)->forWorkspace($workspaceId)->put('members', $user->id, ['id' => $user->id, 'name' => $name, 'email' => $user->email, 'role' => $role]);

        return ['id' => $user->id, 'email' => $user->email];
    }

    /**
     * 以指定成員登入。
     *
     * @param  string  $email  成員Email
     * @return void
     */
    private function statusLogin(string $email): void
    {
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'test-local-password'])->assertOk();
    }

    /**
     * 經Session RPC送出並補requestId。
     *
     * @param  string  $op  RPC操作
     * @param  array<string,mixed>  $input  輸入
     * @return array<string,mixed> 回應data
     */
    private function statusRpc(string $op, array $input): array
    {
        return $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input])->assertOk()->json('data');
    }

    /**
     * 通知預覽入口只在local／testing開放：目前身分帶旗標, 正式環境為false。
     *
     * @return void
     */
    public function test_notification_preview_feature_only_in_local_environments(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'feature-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Feature'])->assertOk();
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.features.notificationPreview', true);

        config(['app.env' => 'production']);
        try {
            $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.features.notificationPreview', false);
        } finally {
            config(['app.env' => 'testing']);
        }
    }

    /**
     * 建卡者A、負責人B, 第三人C改狀態：A、B各收一則；改成已關閉時是重要類型；同requestId重送不重複；改狀態的人自己不收。
     *
     * @return void
     */
    public function test_state_change_notifies_assignee_and_creator(): void
    {
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'status-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Status'])->assertOk()->json('data');
        $workspace = $owner['workspace']['id'];
        $creator = $this->statusMember($workspace, 'Creator');
        $assignee = $this->statusMember($workspace, 'Assignee');
        $changer = $this->statusMember($workspace, 'Changer');
        $project = $this->statusRpc('createProject', ['name' => 'Status', 'code' => 'STS']);

        $this->statusLogin($creator['email']);
        $issue = $this->statusRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Status card', 'assignee' => $assignee['id']]);
        $this->assertSame($creator['id'], (new RecordStore)->forWorkspace($workspace)->require('issues', $issue['id'])['createdBy'], '記下建卡的人');
        $base = DB::table('issue_notifications')->count();

        $this->statusLogin($changer['email']);
        $this->statusRpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'state' => 'inprogress']);
        $rows = DB::table('issue_notifications')->orderBy('id')->skip($base)->take(10)->get();
        $this->assertCount(2, $rows, '負責人和建卡的人各一則');
        $this->assertEqualsCanonicalizing([$creator['email'], $assignee['email']], $rows->pluck('email')->all());
        $this->assertSame(['status'], $rows->pluck('kind')->unique()->values()->all());
        $this->assertStringContainsString('Changer 將狀態從 待規劃 改成 進行中', $rows[0]->body);

        $closeInput = ['requestId' => (string) Str::uuid(), 'id' => $issue['id'], 'expectedRevision' => $issue['revision'] + 1, 'state' => 'closed'];
        $this->postJson('/api/rpc/updateIssue', $closeInput)->assertOk();
        $this->postJson('/api/rpc/updateIssue', $closeInput)->assertOk();
        $closed = DB::table('issue_notifications')->orderBy('id')->skip($base + 2)->take(10)->get();
        $this->assertCount(2, $closed, '同requestId重送不重複產生');
        $this->assertSame(['status_important'], $closed->pluck('kind')->unique()->values()->all());
        $this->assertStringContainsString('已關閉', $closed[0]->subject);

        // 負責人自己改：只通知建卡的人；建卡者＝負責人時只一則且不發給自己。
        $this->statusLogin($assignee['email']);
        $this->statusRpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'] + 2, 'state' => 'open']);
        $own = DB::table('issue_notifications')->orderBy('id')->skip($base + 4)->take(10)->get();
        $this->assertSame([$creator['email']], $own->pluck('email')->all());
        $mine = $this->statusRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Self card', 'assignee' => $assignee['id']]);
        $before = DB::table('issue_notifications')->count();
        $this->statusLogin($changer['email']);
        $this->statusRpc('updateIssue', ['id' => $mine['id'], 'expectedRevision' => $mine['revision'], 'state' => 'inprogress']);
        $this->assertSame([$assignee['email']], DB::table('issue_notifications')->orderBy('id')->skip($before)->take(10)->pluck('email')->all(), '建卡者＝負責人只一則');

        // 通知列表回傳類型。
        $this->statusLogin($assignee['email']);
        $kinds = array_column($this->getJson('/api/v1/projects/'.$project['id'].'/notifications?perPage=100')->assertOk()->json('data.items'), 'kind');
        $this->assertContains('status_important', $kinds);
        $this->assertContains('assigned', $kinds);
    }

    /**
     * 沒有記建卡者的舊卡：改狀態只通知負責人, 不出錯。
     *
     * @return void
     */
    public function test_state_change_on_card_without_creator(): void
    {
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'legacy-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Legacy'])->assertOk()->json('data');
        $assignee = $this->statusMember($owner['workspace']['id'], 'Assignee');
        $project = $this->statusRpc('createProject', ['name' => 'Legacy', 'code' => 'LGC']);
        $issue = $this->statusRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Old card', 'assignee' => $assignee['id']]);
        DB::table('issues')->where('public_id', $issue['id'])->update(['created_by_id' => null]);
        $before = DB::table('issue_notifications')->count();
        $this->statusRpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'state' => 'closed']);
        $this->assertSame([$assignee['email']], DB::table('issue_notifications')->orderBy('id')->skip($before)->take(10)->pluck('email')->all());
    }
}
