<?php

namespace Tests\Concerns;

use App\Models\Issue;
use App\Models\IssueNotification;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 以相同資料範圍斷言驗證memory與獨立MySQL的專案管理查詢。 */
trait ProjectUtilityReadCases
{
    /**
     * 狀態用量只在SQL聚合目前Project，不讀卡片描述或活動；無狀態設定資格者拒絕。
     *
     * @return void
     */
    public function test_status_usage_is_project_scoped_aggregate(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Usage', 'email' => 'usage-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Usage'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Usage', 'code' => 'USAGE'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk()->json('data');
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Open', 'description' => str_repeat('large description ', 300)])->assertOk();
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Working', 'state' => 'inprogress'])->assertOk();
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Working 2', 'state' => 'inprogress'])->assertOk();
        $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Other', 'state' => 'closed'])->assertOk();
        $path = '/api/v1/projects/'.$project['id'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $usage = $this->getJson($path.'/status-usage')->assertOk()->json('data.usage');
        $this->assertSame(['inprogress' => 2, 'open' => 1], collect($usage)->sortKeys()->all(), '只計目前Project，未使用狀態不列');
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'audit_events') || str_contains($q['query'], 'description')));
        $this->getJson($path.'/status-usage', ['X-Workspace-ID' => 'wrong'])->assertStatus(409);
        $reader = User::create(['id' => (string) Str::uuid(), 'name' => 'Reader', 'email' => 'reader-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        (new RecordStore)->forWorkspace($identity['workspace']['id'])->put('members', $reader->id, ['id' => $reader->id, 'name' => $reader->name, 'email' => $reader->email, 'role' => 'user']);
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'roleId' => 'reader', 'expectedRevision' => 0])->assertOk();
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $reader->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson($path.'/status-usage')->assertStatus(403);
        $this->getJson('/api/v1/projects/'.$other['id'].'/status-usage')->assertStatus(404);
    }

    /**
     * 通知摘要不讀正文；收件人與撤權限制在SQL執行。
     *
     * @return void
     */
    public function test_notification_projection_is_project_scoped_and_bounded(): void
    {
        $email = 'utility-'.Str::uuid().'@example.test';
        $identity = $this->postJson('/api/auth/register', ['name' => 'Utility', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => 'Utility'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Utility', 'code' => 'UTILITY'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Scoped', 'description' => str_repeat('large description ', 300)])->assertOk()->json('data');
        $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Other'])->assertOk();
        $path = '/api/v1/projects/'.$project['id'];
        DB::enableQueryLog();
        $p = Project::where('public_id', $project['id'])->firstOrFail();
        $i = Issue::where('public_id', $issue['id'])->firstOrFail();
        $recipient = WorkspaceMember::where('workspace_id', $p->workspace_id)->where('user_id', $identity['user']['id'])->firstOrFail();
        $reader = User::create(['id' => (string) Str::uuid(), 'name' => 'Reader', 'email' => 'reader-'.Str::uuid().'@example.test', 'password' => Hash::make('test-local-password')]);
        (new RecordStore)->forWorkspace($identity['workspace']['id'])->put('members', $reader->id, ['id' => $reader->id, 'name' => $reader->name, 'email' => $reader->email, 'role' => 'user']);
        $this->rpc('setProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'roleId' => 'reader', 'expectedRevision' => 0])->assertOk();
        $readerMember = WorkspaceMember::where('workspace_id', $p->workspace_id)->where('user_id', $reader->id)->firstOrFail();
        $ids = [];
        for ($index = 0; $index < 25; $index++) {
            $id = (string) Str::uuid();
            $ids[] = $id;
            IssueNotification::create(['public_id' => $id, 'workspace_id' => $p->workspace_id, 'project_id' => $p->id, 'issue_id' => $i->id,
                'recipient_id' => $index < 20 ? $recipient->id : $readerMember->id, 'email' => $index < 20 ? $email : $reader->email,
                'subject' => 'Notification '.$index, 'body' => str_repeat('private notification body ', 2000), 'status' => 'preview', 'created_at' => now()]);
        }
        DB::flushQueryLog();
        $page = $this->getJson($path.'/notifications')->assertOk()->assertJsonPath('data.total', 25)->assertJsonCount(20, 'data.items')->json('data.items');
        foreach ($page as $row) {
            $this->assertArrayNotHasKey('body', $row);
            $this->assertSame($issue['id'], $row['issueId']);
            $this->assertSame('UTILITY-1', $row['identifier']);
        }
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'audit_events') || str_contains($q['query'], 'description') || str_contains($q['query'], '"body"') || str_contains($q['query'], '`body`')));
        $next = $this->getJson($path.'/notifications?page=2')->assertOk()->assertJsonCount(5, 'data.items')->json('data.items');
        $this->assertSame([], array_intersect(array_column($page, 'id'), array_column($next, 'id')));
        $this->getJson($path.'/notifications/'.$ids[0])->assertOk()->assertJsonPath('data.body', str_repeat('private notification body ', 2000));
        $this->getJson('/api/v1/projects/'.$other['id'].'/notifications/'.$ids[0])->assertStatus(404);
        $this->getJson($path.'/notifications?perPage=101')->assertStatus(400);
        $this->getJson($path.'/notifications', ['X-Workspace-ID' => 'wrong'])->assertStatus(409);
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $reader->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson($path.'/notifications')->assertOk()->assertJsonPath('data.total', 5);
        $this->getJson($path.'/notifications/'.$ids[0])->assertStatus(404);
        $this->getJson($path.'/notifications/'.$ids[24])->assertOk();
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'test-local-password'])->assertOk();
        $this->rpc('removeProjectMember', ['projectId' => $project['id'], 'userId' => $reader->id, 'expectedRevision' => 1])->assertOk();
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $reader->email, 'password' => 'test-local-password'])->assertOk();
        $this->getJson($path.'/notifications')->assertStatus(404);
        $this->getJson($path.'/notifications/'.$ids[24])->assertStatus(404);
    }
}
