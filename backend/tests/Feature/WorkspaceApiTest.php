<?php

namespace Tests\Feature;

use App\Authorization\WorkspacePermissions;
use App\Domain\Fields;
use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'josh@example.test'): array
    {
        Auth::logout();
        return $this->postJson('/api/auth/register', ['name' => 'Josh', 'email' => $email, 'password' => 'test-local-password', 'workspaceName' => '產品團隊'])->assertOk()->json('data');
    }

    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    private function project(string $code = 'TEST'): array
    {
        return $this->rpc('createProject', ['name' => '專案', 'code' => $code])->assertOk()->json('data');
    }

    private function issue(array $project, array $fields = []): array
    {
        return $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '登入任務', ...$fields])->assertOk()->json('data');
    }

    public function test_authentication_session_logout_and_tenant_isolation(): void
    {
        $this->rpc('getWorkspace')->assertStatus(401);
        $first = $this->register();
        $project = $this->project();
        $issue = $this->issue($project);
        $this->register('other@example.test');
        $this->rpc('getWorkspace')->assertJsonCount(0, 'data.projects');
        $this->rpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => 1, 'title' => '越權'])->assertStatus(404);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->rpc('getWorkspace')->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'josh@example.test', 'password' => 'test-local-password'])->assertOk();
        $this->rpc('getWorkspace')->assertJsonPath('data.meta.workspace.id', $first['workspace']['id'])->assertJsonCount(1, 'data.projects');
    }

    public function test_idempotent_retries_revision_conflicts_and_validation(): void
    {
        $this->register();
        $input = ['name' => '網站', 'code' => 'WEB', 'requestId' => 'create-project'];
        $project = $this->rpc('createProject', $input)->assertOk()->json('data');
        $this->rpc('createProject', $input)->assertJsonPath('data.id', $project['id']);
        $this->rpc('createProject', [...$input, 'name' => '變更'])->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_CONFLICT');
        $this->rpc('createProject', ['name' => '重複', 'code' => 'WEB'])->assertStatus(409);
        $issue = $this->issue($project);
        $this->rpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => 1, 'title' => '新標題'])->assertJsonPath('data.revision', 2);
        $this->rpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => 1, 'title' => '覆寫'])->assertStatus(409);
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '日期', 'dueDate' => '2026-02-30'])->assertStatus(400);
        $this->rpc('addComment', ['id' => $issue['id'], 'expectedRevision' => 2, 'content' => '<script>unsafe</script>'])->assertJsonPath('data.comments.0.content', '<script>unsafe</script>');
    }

    public function test_epic_status_categories_and_time_rules(): void
    {
        $this->register();
        $project = $this->project();
        $epic = $this->issue($project, ['type' => 'epic']);
        $task = $this->issue($project, ['epicId' => $epic['id'], 'estimateMinutes' => 60]);
        $this->rpc('updateIssue', ['id' => $epic['id'], 'expectedRevision' => 1, 'state' => 'done'])->assertStatus(400);
        $this->rpc('logTime', ['issueId' => $epic['id'], 'workDate' => '2026-01-01', 'minutes' => 30])->assertStatus(400);
        $entry = $this->rpc('logTime', ['issueId' => $task['id'], 'workDate' => '2026-01-01', 'minutes' => 60])->assertOk()->json('data');
        $this->rpc('logTime', ['issueId' => $task['id'], 'workDate' => '2026-01-01', 'minutes' => 1400])->assertStatus(400);
        $this->rpc('logTime', ['issueId' => $task['id'], 'workDate' => now()->addYear()->format('Y-m-d'), 'minutes' => 60])->assertStatus(400);
        $this->rpc('updateTime', ['id' => $entry['id'], 'expectedRevision' => 1, 'workDate' => '2026-01-02', 'minutes' => 90])->assertJsonPath('data.revision', 2);
        $this->rpc('voidTime', ['id' => $entry['id'], 'expectedRevision' => 2])->assertJsonPath('data.revision', 3);
        $this->rpc('updateIssue', ['id' => $task['id'], 'expectedRevision' => 1, 'state' => 'done'])->assertOk();
        $this->rpc('updateIssue', ['id' => $epic['id'], 'expectedRevision' => 1, 'state' => 'done'])->assertOk();
        $this->rpc('updateIssue', ['id' => $task['id'], 'expectedRevision' => 2, 'state' => 'todo'])->assertOk();
        $this->rpc('getWorkspace')->assertJsonPath('data.issues.0.state', 'todo');
        $states = [...Fields::STATES, ['id' => 'accepted', 'label' => '驗收', 'color' => '#123456', 'category' => 'done']];
        $this->rpc('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => 1, 'statuses' => $states])->assertOk();
        $this->rpc('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => 1, 'statuses' => $states])->assertStatus(409);
    }

    public function test_dependency_dag_canceled_blocking_and_cross_project_rejection(): void
    {
        $this->register();
        $project = $this->project();
        $other = $this->project('OTHER');
        $first = $this->issue($project);
        $second = $this->issue($project, ['blockedBy' => [$first['id']]]);
        $external = $this->issue($other);
        $epic = $this->issue($project, ['type' => 'epic']);
        foreach ([[$second['id']], [$first['id']], [$external['id']], [$epic['id']]] as $ids) $this->rpc('updateIssue', ['id' => $first['id'], 'expectedRevision' => 1, 'blockedBy' => $ids])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $second['id'], 'expectedRevision' => 1, 'state' => 'done'])->assertStatus(400);
        $states = [...Fields::STATES, ['id' => 'cancel', 'label' => '取消', 'color' => '#123456', 'category' => 'canceled']];
        $this->rpc('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => 1, 'statuses' => $states])->assertOk();
        $this->rpc('updateIssue', ['id' => $first['id'], 'expectedRevision' => 1, 'state' => 'cancel'])->assertOk();
        $this->rpc('updateIssue', ['id' => $second['id'], 'expectedRevision' => 1, 'state' => 'done'])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $first['id'], 'expectedRevision' => 2, 'state' => 'done'])->assertOk();
        $this->rpc('updateIssue', ['id' => $second['id'], 'expectedRevision' => 1, 'state' => 'done'])->assertOk();
    }

    public function test_invite_member_author_permissions_private_views_and_revocation(): void
    {
        $owner = $this->register();
        $project = $this->project();
        $task = $this->issue($project);
        $private = $this->rpc('createSavedView', ['projectId' => $project['id'], 'name' => '私有', 'filters' => []])->assertOk()->json('data');
        $team = $this->rpc('createSavedView', ['projectId' => $project['id'], 'name' => '共用', 'visibility' => 'team', 'filters' => []])->assertOk()->json('data');
        $time = $this->rpc('logTime', ['issueId' => $task['id'], 'workDate' => '2026-01-01', 'minutes' => 30])->assertOk()->json('data');
        $invite = $this->rpc('inviteMember', ['email' => 'mei@example.test'])->assertOk()->json('data');
        Auth::logout();
        $member = $this->postJson('/api/auth/register', ['name' => 'Mei', 'email' => 'mei@example.test', 'password' => 'test-local-password', 'inviteToken' => $invite['token']])->assertOk()->json('data');
        $this->rpc('getWorkspace')->assertJsonCount(1, 'data.savedViews')->assertJsonPath('data.savedViews.0.id', $team['id']);
        $this->rpc('updateSavedView', ['id' => $team['id'], 'expectedRevision' => 1, 'name' => '覆寫', 'filters' => []])->assertStatus(404);
        $this->rpc('deleteSavedView', ['id' => $private['id'], 'expectedRevision' => 1])->assertStatus(404);
        $this->rpc('voidTime', ['id' => $time['id'], 'expectedRevision' => 1])->assertStatus(403);
        $this->rpc('inviteMember', ['email' => 'new@example.test'])->assertStatus(403);
        $this->rpc('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => 1, 'statuses' => Fields::STATES])->assertStatus(403);
        $this->rpc('smtpStatus')->assertStatus(403);
        $retry = ['requestId' => 'member-retry', 'projectId' => $project['id'], 'title' => '自己的任務'];
        $this->rpc('createIssue', $retry)->assertOk();
        DB::table('records')->where('kind', 'w:'.$owner['workspace']['id'].':members')->where('id', $member['user']['id'])->delete();
        $this->rpc('createIssue', $retry)->assertStatus(401);
    }

    public function test_invalid_input_boundary_demo_and_unknown_role_deny(): void
    {
        $context = $this->register();
        $this->withHeader('Origin', 'https://evil.test')->rpc('getWorkspace')->assertStatus(403);
        $this->flushHeaders();
        $this->rpc('unknown')->assertStatus(404);
        $this->rpc('loadDemo')->assertOk();
        $this->rpc('getWorkspace')->assertJsonCount(9, 'data.issues');
        $this->rpc('loadDemo')->assertStatus(409);
        $this->assertSame([], WorkspacePermissions::resolve('unknown'));
        $tenant = (new RecordStore)->forWorkspace($context['workspace']['id']);
        $member = $tenant->require('members', $context['user']['id']);
        $tenant->put('members', $member['id'], [...$member, 'role' => 'unknown']);
        $this->rpc('getWorkspace')->assertStatus(403);
    }
}
