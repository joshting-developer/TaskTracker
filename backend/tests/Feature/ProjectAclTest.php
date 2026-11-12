<?php

namespace Tests\Feature;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Models\User;
use App\Repositories\RecordStore;
use App\Services\ProjectAclUpgrade;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectAclTest extends TestCase
{
    use RefreshDatabase;

    private array $owner;

    private RecordStore $tenant;

    private array $first;

    private array $second;

    private array $privateIssue;

    private array $privateBoard;

    private array $privateTime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'acl-owner@example.test', 'password' => 'test-local-password', 'workspaceName' => 'ACL 空間'])->assertOk()->json('data');
        $this->tenant = (new RecordStore)->forWorkspace($this->owner['workspace']['id']);
        $this->first = $this->rpc('createProject', ['name' => '公開給指定成員', 'code' => 'FIRST'])->assertOk()->json('data');
        $this->second = $this->rpc('createProject', ['name' => '私有薪資專案', 'code' => 'SECRET'])->assertOk()->json('data');
        $this->privateIssue = $this->rpc('createIssue', ['projectId' => $this->second['id'], 'title' => '不可洩漏的薪資', 'type' => 'epic'])->assertOk()->json('data');
        $task = $this->rpc('createIssue', ['projectId' => $this->second['id'], 'title' => '薪資子卡', 'epicId' => $this->privateIssue['id']])->assertOk()->json('data');
        $this->privateTime = $this->rpc('logTime', ['issueId' => $task['id'], 'workDate' => '2026-01-01', 'minutes' => 30])->assertOk()->json('data');
        $this->privateBoard = $this->rpc('createBoard', ['projectId' => $this->second['id'], 'name' => '薪資板', 'selectedIssueIds' => [$this->privateIssue['id']]])->assertOk()->json('data');
    }

    private function rpc(string $op, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    private function member(string $name, string $role = 'user'): array
    {
        $user = User::create(['id' => (string) Str::uuid(), 'name' => $name, 'email' => $name.'@example.test', 'password' => Hash::make('test-local-password')]);
        $member = ['id' => $user->id, 'name' => $name, 'email' => $user->email, 'role' => $role];
        $this->tenant->put('members', $user->id, $member);

        return $member;
    }

    private function login(array $member): void
    {
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => $member['email'], 'password' => 'test-local-password'])->assertOk();
    }

    private function asOwner(): void
    {
        $this->login($this->owner['user']);
    }

    private function grant(array $member, string $role = 'developer', ?string $projectId = null): void
    {
        $this->asOwner();
        $projectId ??= $this->first['id'];
        $revision = $this->rpc('getProjectMembers', ['projectId' => $projectId])->assertOk()->json('data.revision');
        $this->rpc('setProjectMember', ['projectId' => $projectId, 'userId' => $member['id'], 'roleId' => $role, 'expectedRevision' => $revision])->assertOk();
    }

    public function test_unjoined_and_readonly_members_cannot_see_private_resources_or_write(): void
    {
        $user = $this->member('reader');
        $this->login($user);
        $this->rpc('getWorkspace')->assertOk()->assertJsonCount(0, 'data.projects')->assertJsonCount(0, 'data.issues')->assertJsonCount(0, 'data.boards')->assertJsonCount(0, 'data.timeEntries')->assertJsonCount(0, 'data.notifications')->assertJsonCount(1, 'data.members');
        $this->grant($user, 'reader');
        $this->login($user);
        $data = $this->rpc('getWorkspace')->assertOk()->json('data');
        $this->assertSame([$this->first['id']], array_column($data['projects'], 'id'));
        $this->assertSame(ProjectAccess::READ, $data['projects'][0]['permissions']);
        $this->assertSame('project', $data['projects'][0]['accessSource']);
        $this->assertSame([], $data['timeEntries']);
        foreach ([$this->second['id'], $this->privateIssue['id'], '薪資', $this->privateTime['id'], $this->privateBoard['id']] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($data));
        }
        $this->rpc('createIssue', ['projectId' => $this->first['id'], 'title' => '禁止'])->assertStatus(403);
        $this->rpc('setProjectMember', ['projectId' => $this->first['id'], 'userId' => $user['id'], 'roleId' => 'lead', 'expectedRevision' => 1])->assertStatus(403);
        foreach ([['updateProject', ['id' => $this->second['id']]], ['createIssue', ['projectId' => $this->second['id']]], ['updateIssue', ['id' => $this->privateIssue['id'], 'projectId' => $this->first['id']]], ['addComment', ['id' => $this->privateIssue['id']]], ['updateBoard', ['id' => $this->privateBoard['id'], 'projectId' => $this->first['id']]], ['createBoard', ['projectId' => $this->second['id']]], ['updateStatuses', ['projectId' => $this->second['id']]], ['logTime', ['issueId' => $this->privateIssue['id']]], ['updateTime', ['id' => $this->privateTime['id']]], ['voidTime', ['id' => $this->privateTime['id']]], ['getProjectMembers', ['projectId' => $this->second['id']]]] as [$op, $input]) {
            $response = $this->rpc($op, $input)->assertStatus(404);
            $this->assertStringNotContainsString('薪資', $response->getContent());
        }
    }

    public function test_fixed_project_roles_and_permission_scope_not_account_name_control_operations(): void
    {
        foreach (['lead', 'maintainer', 'developer', 'reader'] as $role) {
            $member = $this->member('role-'.$role);
            $this->grant($member, $role);
            $this->login($member);
            $manage = in_array('project.members.manage', ProjectAccess::rolePermissions($role), true);
            $write = in_array('issue.write', ProjectAccess::rolePermissions($role), true);
            $project = $this->tenant->require('projects', $this->first['id']);
            $this->rpc('updateProject', ['id' => $project['id'], 'description' => $role, 'expectedRevision' => $project['revision']])->assertStatus($manage ? 200 : 403);
            $this->rpc('getProjectMembers', ['projectId' => $project['id']])->assertStatus($manage ? 200 : 403);
            $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => $role])->assertStatus($manage ? 200 : 403);
            $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => $role])->assertStatus($write ? 200 : 403);
            $this->rpc('createProject', ['name' => '不允許', 'code' => 'NOPE'])->assertStatus(403);
            $this->rpc('inviteMember', ['email' => 'no-invite@example.test'])->assertStatus(403);
        }
        $manager = $this->member('ws-manager', 'manager');
        $this->login($manager);
        $this->rpc('getWorkspace')->assertJsonCount(2, 'data.projects');
        $this->rpc('createIssue', ['projectId' => $this->second['id'], 'title' => '全專案範圍'])->assertOk();
        $this->rpc('voidTime', ['id' => $this->privateTime['id'], 'expectedRevision' => 1])->assertStatus(403);
        $this->tenant->put('members', $manager['id'], [...$manager, 'role' => 'user']);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.projects');
        // 頁面名稱改成 owner, 或 payload 自稱全權, 不改變有效授權
        User::find($manager['id'])->update(['name' => 'owner']);
        $this->rpc('createIssue', ['projectId' => $this->second['id'], 'role' => 'owner', 'permissions' => ['issue.writeAny']])->assertStatus(404);
        $this->asOwner();
        $context = [...$this->owner, 'permissions' => ['workspace.read', 'project.readAny']];
        $access = new ProjectAccess($this->tenant, $context);
        $this->assertTrue($access->can($this->first['id'], 'project.read'));
        $this->assertFalse($access->can($this->first['id'], 'issue.write'));
        $this->assertFalse($access->can($this->first['id'], 'typo.permission'));
        $this->expectException(DomainError::class);
        DB::transaction(fn () => app(WorkspaceService::class)->execute($context, 'createIssue', ['projectId' => $this->first['id'], 'title' => '角色名稱不能放行', 'requestId' => 'denied-owner']));
    }

    public function test_membership_revisions_audit_and_revocation_precede_retries(): void
    {
        $member = $this->member('cached');
        $this->grant($member);
        $this->login($member);
        $input = ['projectId' => $this->first['id'], 'title' => '重試卡片', 'requestId' => 'retry-after-revoke'];
        $issue = $this->rpc('createIssue', $input)->assertOk()->json('data');
        $this->rpc('createIssue', $input)->assertJsonPath('data.id', $issue['id']);
        $this->asOwner();
        $listing = $this->rpc('getProjectMembers', ['projectId' => $this->first['id']])->assertOk()->json('data');
        $remove = ['projectId' => $this->first['id'], 'userId' => $member['id'], 'expectedRevision' => $listing['revision'], 'requestId' => 'remove-once'];
        $this->rpc('removeProjectMember', $remove)->assertOk()->assertJsonPath('data.revision', 2);
        $this->rpc('removeProjectMember', $remove)->assertOk()->assertJsonPath('data.revision', 2);
        $this->rpc('setProjectMember', ['projectId' => $this->first['id'], 'userId' => $member['id'], 'roleId' => 'reader', 'expectedRevision' => 1])->assertStatus(409);
        $history = $this->rpc('getProjectMembers', ['projectId' => $this->first['id']])->assertOk()->json('data.history');
        $this->assertCount(2, $history);
        $this->assertSame($this->owner['user']['id'], $history[1]['actorId']);
        $this->login($member);
        $this->rpc('createIssue', $input)->assertStatus(404);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.projects')->assertJsonCount(0, 'data.issues');
        $this->assertSame($issue['title'], $this->tenant->require('issues', $issue['id'])['title']);
        $this->grant($member, 'maintainer');
        $this->login($member);
        $this->rpc('setProjectMember', ['projectId' => $this->first['id'], 'userId' => $member['id'], 'roleId' => 'owner', 'expectedRevision' => 3])->assertStatus(400);
        $this->rpc('setProjectMember', ['projectId' => $this->first['id'], 'userId' => $member['id'], 'roleId' => 'unknown', 'expectedRevision' => 3])->assertStatus(400);
        $this->assertSame('user', $this->tenant->require('members', $member['id'])['role']);
        $this->tenant->put('members', $member['id'], [...$member, 'disabledAt' => now()->toISOString()]);
        $this->rpc('getWorkspace')->assertStatus(401);
    }

    public function test_relations_assignments_notifications_and_time_are_limited_to_the_project(): void
    {
        $developer = $this->member('dev');
        $outsider = $this->member('outside');
        $this->grant($developer);
        $this->login($developer);
        $base = ['projectId' => $this->first['id'], 'title' => '一般工作'];
        $issue = $this->rpc('createIssue', $base)->assertOk()->json('data');
        $this->rpc('createIssue', [...$base, 'assignee' => $outsider['id']])->assertStatus(400);
        $this->rpc('createIssue', [...$base, 'epicId' => $this->privateIssue['id']])->assertStatus(400);
        $this->rpc('createIssue', [...$base, 'relatedIssueIds' => [$this->privateIssue['id']]])->assertStatus(400);
        $this->rpc('createIssue', [...$base, 'parentIssueId' => $this->privateIssue['id']])->assertStatus(400);
        $entry = $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => '2026-01-01', 'minutes' => 15])->assertOk()->json('data');
        $this->rpc('updateTime', ['id' => $entry['id'], 'expectedRevision' => 1, 'workDate' => '2026-01-01', 'minutes' => 20])->assertOk();
        $this->asOwner();
        $this->rpc('voidTime', ['id' => $entry['id'], 'expectedRevision' => 2])->assertStatus(403);
        $this->rpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => 1, 'assignee' => $developer['id']])->assertOk();
        $this->assertNotEmpty($this->tenant->all('notifications'));
        $this->grant($outsider, 'reader');
        $this->login($outsider);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.timeEntries')->assertJsonCount(0, 'data.notifications');
        $this->asOwner();
        $revision = $this->rpc('getProjectMembers', ['projectId' => $this->first['id']])->json('data.revision');
        $this->rpc('removeProjectMember', ['projectId' => $this->first['id'], 'userId' => $developer['id'], 'expectedRevision' => $revision])->assertOk();
        $this->login($developer);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.notifications')->assertJsonCount(0, 'data.timeEntries');
        $this->assertCount(1, $this->tenant->all('notifications'));
        $this->assertCount(2, $this->tenant->all('timeEntries'));
    }

    public function test_role_grants_are_limited_to_the_actors_actual_effective_permissions(): void
    {
        $target = $this->member('limited-target');
        $context = [...$this->owner, 'permissions' => ['workspace.read', 'project.readAny', 'issue.readAny', 'project.members.manageAny']];
        $listing = DB::transaction(fn () => app(WorkspaceService::class)->execute($context, 'getProjectMembers', ['projectId' => $this->first['id']]));
        $this->assertSame(['reader'], array_column($listing['roles'], 'id'));
        DB::transaction(fn () => app(WorkspaceService::class)->execute($context, 'setProjectMember', ['projectId' => $this->first['id'], 'userId' => $target['id'], 'roleId' => 'reader', 'expectedRevision' => 0, 'requestId' => 'limited-reader']));
        $this->assertSame('reader', $this->tenant->require('projectMembers', ProjectAccess::memberId($this->first['id'], $target['id']))['roleId']);
        $this->expectException(DomainError::class);
        $this->expectExceptionMessage('不能指派或調整超出自己有效權限');
        DB::transaction(fn () => app(WorkspaceService::class)->execute($context, 'setProjectMember', ['projectId' => $this->first['id'], 'userId' => $target['id'], 'roleId' => 'developer', 'expectedRevision' => 1, 'requestId' => 'limited-escalate']));
    }

    public function test_upgrade_preserves_business_data_and_does_not_grant_all_legacy_users(): void
    {
        $user = $this->member('legacy');
        $this->tenant->put('projects', $this->first['id'], [...$this->first, 'leadId' => $user['id']]);
        foreach (['old-admin', 'old-member'] as $id) {
            User::create(['id' => $id, 'name' => $id, 'email' => $id.'@example.test', 'password' => 'legacy-test-password']);
        }
        $this->tenant->put('members', 'old-admin', ['id' => 'old-admin', 'name' => '舊管理員', 'role' => 'admin']);
        $this->tenant->put('members', 'old-member', ['id' => 'old-member', 'name' => '舊成員', 'role' => 'member']);
        $before = [$this->tenant->all('projects'), $this->tenant->all('issues'), $this->tenant->all('timeEntries')];
        ProjectAclUpgrade::run();
        $this->assertSame('manager', $this->tenant->require('members', 'old-admin')['role']);
        $this->assertSame('user', $this->tenant->require('members', 'old-member')['role']);
        $this->assertSame([], $this->tenant->all('projectMembers'));
        $after = [$this->tenant->all('projects'), $this->tenant->all('issues'), $this->tenant->all('timeEntries')];
        $this->assertSame($before, $after);
        $snapshot = $this->tenant->all('upgrades');
        ProjectAclUpgrade::run();
        $this->assertSame($snapshot, $this->tenant->all('upgrades'));
        $this->login($user);
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.projects');
    }

    /** 新查詢介面逐請求重驗資格，不能因先前讀過資源而保留撤權後存取。 */
    public function test_scoped_query_api_preserves_reader_isolation_and_revocation(): void
    {
        $reader = $this->member('query-reader');
        $this->grant($reader, 'reader');
        $this->asOwner();
        $issue = $this->rpc('createIssue', ['projectId' => $this->first['id'], 'title' => 'Reader visible'])->assertOk()->json('data');
        $this->login($reader);
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $this->first['id']);
        $this->getJson('/api/v1/project-options')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/project-lead-options')->assertStatus(403);
        $this->getJson('/api/v1/project-lead-options?project='.$this->first['id'])->assertStatus(403);
        $path = '/api/v1/projects/'.$this->first['id'].'/issues/'.$issue['id'];
        $this->getJson($path)->assertOk()->assertJsonPath('data.title', 'Reader visible');
        $this->getJson($path.'/children')->assertOk();
        $this->getJson($path.'/comments')->assertOk();
        $this->getJson($path.'/history')->assertOk();
        $this->getJson($path.'/time')->assertStatus(403);
        $this->getJson($path)->assertJsonMissingPath('data.spentMinutes');
        $this->getJson('/api/v1/projects/'.$this->first['id'].'/member-options')->assertOk()->assertJsonMissingPath('data.items.0.email');
        $this->getJson('/api/v1/projects/'.$this->second['id'])->assertStatus(404);
        $this->getJson('/api/v1/projects/'.$this->second['id'].'/issues')->assertStatus(404);
        $this->getJson('/api/v1/projects/'.$this->second['id'].'/issue-options')->assertStatus(404);
        $this->getJson('/api/v1/projects/'.$this->first['id'].'/issues/'.$this->privateIssue['id'])->assertStatus(404);
        $assignment = $this->tenant->require('projectMembers', ProjectAccess::memberId($this->first['id'], $reader['id']));
        $this->tenant->put('projectMembers', $assignment['id'], [...$assignment, 'removedAt' => now()->toISOString()]);
        $this->getJson($path)->assertStatus(404);
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(0, 'data.items');
    }
}
