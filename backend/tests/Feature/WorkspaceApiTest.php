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

    public function test_project_details_lead_permissions_revisions_and_tenant_boundaries(): void
    {
        $owner = $this->register();
        $project = $this->project();
        $this->assertSame($owner['user']['id'], $project['leadId']);
        $issue = $this->issue($project);
        $change = ['id' => $project['id'], 'expectedRevision' => 1, 'name' => '長期維護專案', 'description' => '負責產品維護', 'leadId' => '', 'requestId' => 'project-update'];
        $this->rpc('updateProject', $change)->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.leadId', '');
        $this->rpc('updateProject', $change)->assertJsonPath('data.revision', 2);
        $this->rpc('updateProject', ['id' => $project['id'], 'expectedRevision' => 1, 'name' => '過期修改'])->assertStatus(409);
        $this->rpc('updateProject', ['id' => $project['id'], 'expectedRevision' => 2, 'leadId' => 'other-member'])->assertStatus(400);
        $this->rpc('updateProject', ['id' => $project['id'], 'expectedRevision' => 2, 'code' => 'NEW'])->assertStatus(400);
        $invite = $this->rpc('inviteMember', ['email' => 'lead@example.test'])->assertOk()->json('data');
        Auth::logout();
        $member = $this->postJson('/api/auth/register', ['name' => 'Lead', 'email' => 'lead@example.test', 'password' => 'test-local-password', 'inviteToken' => $invite['token']])->assertOk()->json('data');
        $this->postJson('/api/auth/login', ['email' => 'josh@example.test', 'password' => 'test-local-password'])->assertOk();
        $this->rpc('updateProject', ['id' => $project['id'], 'expectedRevision' => 2, 'leadId' => $member['user']['id']])->assertOk()->assertJsonPath('data.revision', 3);
        $this->postJson('/api/auth/login', ['email' => 'lead@example.test', 'password' => 'test-local-password'])->assertOk();
        $this->rpc('updateProject', ['id' => $project['id'], 'expectedRevision' => 3, 'description' => '負責人也不能繞過權限'])->assertStatus(403);
        $this->register('other@example.test');
        $this->rpc('updateProject', ['id' => $project['id'], 'expectedRevision' => 3, 'name' => '越權'])->assertStatus(404);
        $this->rpc('createProject', ['name' => '別的空間', 'code' => 'OTHER', 'leadId' => $owner['user']['id']])->assertStatus(400);
        $this->postJson('/api/auth/login', ['email' => 'josh@example.test', 'password' => 'test-local-password'])->assertOk();
        $data = $this->rpc('getWorkspace')->assertOk()->json('data');
        $this->assertSame($issue, $data['issues'][0]);
        $this->assertSame('TEST', $data['projects'][0]['code']);
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

    public function test_subtasks_related_links_revision_retries_and_no_completion_blocking(): void
    {
        $this->register();
        $project = $this->project();
        $other = $this->project('OTHER');
        $epic = $this->issue($project, ['type' => 'epic']);
        $parent = $this->issue($project, ['epicId' => $epic['id']]);
        $child = $this->issue($project, ['parentIssueId' => $parent['id'], 'estimateMinutes' => 60]);
        $this->assertSame($epic['id'], $child['epicId']);
        $external = $this->issue($other);
        foreach ([$child['id'], $parent['id'], $external['id'], $epic['id']] as $id) $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'parentIssueId' => $id])->assertStatus(400);
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => '孫任務', 'parentIssueId' => $child['id']])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'type' => 'epic', 'epicId' => ''])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 1, 'state' => 'done'])->assertOk();
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 2, 'epicId' => ''])->assertOk();
        $this->rpc('updateIssue', ['id' => $child['id'], 'expectedRevision' => 1, 'title' => '舊表單'])->assertStatus(409);
        $input = ['id' => $child['id'], 'expectedRevision' => 2, 'relatedIssueIds' => [$parent['id']], 'requestId' => 'link-retry'];
        $this->rpc('updateIssue', $input)->assertJsonPath('data.revision', 3);
        $this->rpc('updateIssue', $input)->assertJsonPath('data.revision', 3);
        $items = collect($this->rpc('getWorkspace')->assertOk()->json('data.issues'))->keyBy('id');
        $this->assertSame('', $items[$child['id']]['epicId']);
        $this->assertSame($child['state'], $items[$child['id']]['state']);
        $this->assertSame('done', $items[$parent['id']]['state']);
        $this->assertSame([$child['id']], $items[$parent['id']]['relatedIssueIds']);
        $this->assertSame(4, $items[$parent['id']]['revision']);
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 3, 'title' => '舊表單'])->assertStatus(409);
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 4, 'relatedIssueIds' => [$external['id']]])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 4, 'relatedIssueIds' => [$parent['id']]])->assertStatus(400);
        $this->rpc('updateIssue', ['id' => $parent['id'], 'expectedRevision' => 4, 'relatedIssueIds' => []])->assertOk();
        $this->rpc('updateIssue', ['id' => $child['id'], 'expectedRevision' => 4, 'state' => 'done'])->assertOk();
        $this->rpc('updateIssue', ['id' => $child['id'], 'expectedRevision' => 5, 'state' => 'todo'])->assertOk();
        $this->rpc('updateIssue', ['id' => $child['id'], 'expectedRevision' => 6, 'blockedBy' => [$parent['id']]])->assertStatus(400);
        $this->rpc('createSavedView', ['projectId' => $project['id'], 'name' => '舊依賴篩選', 'filters' => ['condition' => 'blocked']])->assertStatus(400);
        $this->rpc('logTime', ['issueId' => $parent['id'], 'workDate' => '2026-01-01', 'minutes' => 30])->assertOk();
        $this->rpc('logTime', ['issueId' => $child['id'], 'workDate' => '2026-01-01', 'minutes' => 60])->assertOk();
        $this->register('other@example.test');
        $this->rpc('createIssue', ['projectId' => $this->project('TENANT')['id'], 'title' => '跨空間', 'parentIssueId' => $parent['id']])->assertStatus(404);
    }

    public function test_dependency_upgrade_is_symmetric_idempotent_and_preserves_history_and_views(): void
    {
        $context = $this->register();
        $project = $this->project();
        $first = $this->issue($project);
        $second = $this->issue($project);
        $store = (new RecordStore)->forWorkspace($context['workspace']['id']);
        foreach ([[$first, []], [$second, [$first['id']]]] as [$issue, $ids]) {
            unset($issue['parentIssueId'], $issue['relatedIssueIds']);
            $store->put('issues', $issue['id'], [...$issue, 'blockedBy' => $ids]);
        }
        $store->put('savedViews', 'old-view', ['id' => 'old-view', 'filters' => ['condition' => 'review']]);
        $store->put('timeEntries', 'old-time', ['id' => 'old-time', 'issueId' => $first['id'], 'minutes' => 30]);
        $otherStore = (new RecordStore)->forWorkspace('other-workspace');
        $otherStore->put('issues', $first['id'], [...$first, 'blockedBy' => [$second['id']]]);
        $migration = require database_path('migrations/2026_10_20_000000_replace_issue_dependencies.php');
        $migration->up();
        $converted = $store->require('issues', $second['id']);
        $this->assertArrayNotHasKey('blockedBy', $converted);
        $this->assertSame([$first['id']], $converted['relatedIssueIds']);
        $this->assertSame([$second['id']], $store->require('issues', $first['id'])['relatedIssueIds']);
        $this->assertSame(2, $converted['revision']);
        $this->assertSame($second['history'][0], $converted['history'][0]);
        $this->assertSame([$first['id']], $converted['history'][1]['changes'][1]['before']);
        $this->assertSame([], $otherStore->require('issues', $first['id'])['relatedIssueIds']);
        $this->assertSame('review', $store->require('savedViews', 'old-view')['filters']['condition']);
        $this->assertSame(30, $store->require('timeEntries', 'old-time')['minutes']);
        $migration->up();
        $this->assertSame($converted, $store->require('issues', $second['id']));
        $this->rpc('updateIssue', ['id' => $second['id'], 'expectedRevision' => 2, 'state' => 'done'])->assertOk();
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
