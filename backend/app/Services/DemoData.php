<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoData
{
    public const EMAIL = 'owner@example.test';
    public const USER_ID = '00000000-0000-4000-8000-000000000201';
    public const WORKSPACE_ID = 'tasktracker-seed-f-ws';

    public const ACCOUNTS = [
        'owner' => ['id' => self::USER_ID, 'email' => self::EMAIL],
        'manager' => ['id' => '00000000-0000-4000-8000-000000000202', 'email' => 'manager@example.test'],
        'maintainer' => ['id' => '00000000-0000-4000-8000-000000000203', 'email' => 'maintainer@example.test'],
        'developer' => ['id' => '00000000-0000-4000-8000-000000000204', 'email' => 'developer@example.test'],
    ];

    public function seed(bool $reset = false, ?string $password = null): array
    {
        if (!app()->environment('local', 'testing')) throw new \RuntimeException('示範 seed 僅供 local／testing 環境');
        return DB::transaction(function () use ($reset, $password) {
            $store = new RecordStore;
            $marker = $store->get('demoSeeds', self::WORKSPACE_ID);
            if (($marker['version'] ?? 0) >= 2 && !$reset) { ProjectAclUpgrade::run(); return ['created' => false, 'upgraded' => false, 'workspaceId' => self::WORKSPACE_ID]; }
            foreach (self::ACCOUNTS as $account) {
                $existing = User::where('email', $account['email'])->orWhere('id', $account['id'])->first();
                $member = $store->forWorkspace(self::WORKSPACE_ID)->get('members', $account['id']);
                if ($existing && ($existing->id !== $account['id'] || $existing->email !== $account['email'] || !$marker || !$member)) throw new \RuntimeException('示範信箱或 ID 已被其他帳號使用, 不覆蓋既有帳號');
            }
            if (!$marker && $store->get('workspaces', self::WORKSPACE_ID)) throw new \RuntimeException('示範空間 ID 已被使用, 不覆蓋既有空間');
            $password ??= env('TASKTRACKER_SEED_PASSWORD');
            if (!is_string($password) || strlen($password) < 10) throw new \RuntimeException('請在本機環境設定 TASKTRACKER_SEED_PASSWORD（至少 10 字）, 不將密碼提交 Git');
            if ($reset && $marker) {
                // 精確限定這組 seed namespace, 不清空資料庫或其他 Workspace
                $store->forWorkspace(self::WORKSPACE_ID)->clearWorkspace();
                DB::table('sessions')->whereIn('user_id', array_column(self::ACCOUNTS, 'id'))->delete();
            }
            $workspace = $store->get('workspaces', self::WORKSPACE_ID) ?? ['id' => self::WORKSPACE_ID, 'name' => 'f-ws', 'createdAt' => now()->utc()->toISOString()];
            $store->put('workspaces', self::WORKSPACE_ID, $workspace);
            $tenant = $store->forWorkspace(self::WORKSPACE_ID);
            $services = [];
            foreach (self::ACCOUNTS as $role => $account) {
                $user = User::updateOrCreate(['id' => $account['id']], ['name' => $role, 'email' => $account['email'], 'password' => Hash::make($password)]);
                $tenant->put('members', $user->id, ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role, 'joinedAt' => now()->utc()->toISOString()]);
                $services[$role] = new WorkspaceService(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email], 'workspace' => $workspace, 'role' => $role, 'permissions' => WorkspacePermissions::resolve($role)]);
            }
            if ($marker && !$reset) {
                ProjectAclUpgrade::seedAssignments($tenant);
                ProjectAclUpgrade::run();
                $store->put('demoSeeds', self::WORKSPACE_ID, [...$marker, 'version' => 2, 'upgradedAt' => now()->utc()->toISOString()]);
                return ['created' => false, 'upgraded' => true, 'workspaceId' => self::WORKSPACE_ID];
            }
            $service = $services['owner'];
            $run = fn ($op, $input) => $service->execute($op, [...$input, 'requestId' => (string) Str::uuid()]);
            foreach (['first', 'second'] as $index => $name) {
                $project = $run('createProject', ['name' => $name, 'code' => strtoupper($name), 'description' => $name.' 的示範工作, 可自由修改；重設 seed 可還原']);
                $run('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => 1, 'statuses' => [...$project['statuses'], ['id' => 'review', 'label' => '待驗收', 'category' => 'active', 'color' => '#8757b3']]]);
                ProjectAclUpgrade::seedAssignments($tenant);
                // 非開發者成員不能在 second 被指派新工作
                $create = fn ($title, $fields = []) => $run('createIssue', ['projectId' => $project['id'], 'title' => $title, 'assignee' => self::ACCOUNTS[$index ? 'maintainer' : 'developer']['id'], ...$fields]);
                $epic = $create($index ? '正式上線準備' : '會員功能第一版', ['type' => 'epic', 'assignee' => self::ACCOUNTS['manager']['id']]);
                $parent = $create($index ? '完成上線前檢查' : '完成登入功能', ['epicId' => $epic['id'], 'estimateMinutes' => 60, 'state' => 'in_progress', 'assignee' => self::ACCOUNTS['maintainer']['id']]);
                $api = $create($index ? '檢查 API 健康狀態' : '實作登入 API', ['parentIssueId' => $parent['id'], 'estimateMinutes' => 120, 'state' => 'review']);
                $ui = $create($index ? '確認手機畫面' : '完成登入畫面', ['parentIssueId' => $parent['id'], 'estimateMinutes' => 90, 'state' => 'todo']);
                $bug = $create($index ? '修正通知文案' : '修正表單錯誤提示', ['type' => 'bug', 'epicId' => $epic['id'], 'estimateMinutes' => 30, 'priority' => 'high', 'relatedIssueIds' => [$parent['id']], 'dueDate' => now('Asia/Taipei')->subDay()->toDateString()]);
                $create($index ? '整理發布說明' : '整理操作說明', ['type' => 'task', 'state' => 'done', 'epicId' => $epic['id'], 'estimateMinutes' => 60]);
                $create('討論下一次迭代範圍', ['state' => 'backlog', 'assignee' => '']);
                foreach ([[$parent, 30, 2, 'maintainer'], [$api, 90, 1, $index ? 'maintainer' : 'developer'], [$ui, 45, 0, $index ? 'maintainer' : 'developer']] as [$issue, $minutes, $daysAgo, $role]) {
                    $account = self::ACCOUNTS[$role];
                    $member = $tenant->require('members', $account['id']);
                    $worker = new WorkspaceService(['user' => ['id' => $account['id'], 'name' => $role], 'workspace' => $workspace, 'role' => $member['role'], 'permissions' => WorkspacePermissions::resolve($member['role'])]);
                    $worker->execute('logTime', ['requestId' => (string) Str::uuid(), 'issueId' => $issue['id'], 'workDate' => now('Asia/Taipei')->subDays($daysAgo)->toDateString(), 'minutes' => $minutes, 'note' => 'Seed 示範工時']);
                }
                $latest = $tenant->require('issues', $bug['id']);
                $run('addComment', ['id' => $bug['id'], 'expectedRevision' => $latest['revision'], 'content' => '已補上重現步驟, 請協助確認']);
            }
            ProjectAclUpgrade::run();
            $store->put('demoSeeds', self::WORKSPACE_ID, ['id' => self::WORKSPACE_ID, 'version' => 2, 'seededAt' => now()->utc()->toISOString()]);
            return ['created' => true, 'upgraded' => false, 'workspaceId' => self::WORKSPACE_ID];
        });
    }
}
