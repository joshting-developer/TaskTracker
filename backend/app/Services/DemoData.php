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
    public const EMAIL = 'jack100398@gmail.com';
    public const USER_ID = '00000000-0000-4000-8000-000000000101';
    public const WORKSPACE_ID = 'tasktracker-seed-f-ws';

    public function seed(bool $reset = false, ?string $password = null): array
    {
        if (!app()->environment('local', 'testing')) throw new \RuntimeException('示範 seed 僅供 local／testing 環境');
        return DB::transaction(function () use ($reset, $password) {
            $store = new RecordStore;
            $marker = $store->get('demoSeeds', self::WORKSPACE_ID);
            if ($marker && !$reset) return ['created' => false, 'workspaceId' => self::WORKSPACE_ID];
            $existing = User::where('email', self::EMAIL)->orWhere('id', self::USER_ID)->first();
            if ($existing && ($existing->id !== self::USER_ID || $existing->email !== self::EMAIL)) throw new \RuntimeException('示範信箱或 ID 已被其他帳號使用, 不覆蓋既有帳號');
            if (!$marker && $store->get('workspaces', self::WORKSPACE_ID)) throw new \RuntimeException('示範空間 ID 已被使用, 不覆蓋既有空間');
            $password ??= env('TASKTRACKER_SEED_PASSWORD');
            if (!is_string($password) || strlen($password) < 10) throw new \RuntimeException('請在本機環境設定 TASKTRACKER_SEED_PASSWORD（至少 10 字）, 不將密碼提交 Git');
            if ($reset && $marker) {
                // 精確限定這組 seed namespace, 不清空資料庫或其他 Workspace
                DB::table('records')->where('kind', 'like', 'w:'.self::WORKSPACE_ID.':%')->delete();
                DB::table('sessions')->where('user_id', self::USER_ID)->delete();
            }
            $user = User::updateOrCreate(['id' => self::USER_ID], ['name' => 'Jack', 'email' => self::EMAIL, 'password' => Hash::make($password)]);
            $workspace = ['id' => self::WORKSPACE_ID, 'name' => 'f-ws', 'createdAt' => now()->utc()->toISOString()];
            $store->put('workspaces', self::WORKSPACE_ID, $workspace);
            $tenant = $store->forWorkspace(self::WORKSPACE_ID);
            $tenant->put('members', $user->id, ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'owner', 'joinedAt' => now()->utc()->toISOString()]);
            $service = new WorkspaceService(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email], 'workspace' => $workspace, 'role' => 'owner', 'permissions' => WorkspacePermissions::resolve('owner')]);
            $run = fn ($op, $input) => $service->execute($op, [...$input, 'requestId' => (string) Str::uuid()]);
            foreach (['first', 'second'] as $index => $name) {
                $project = $run('createProject', ['name' => $name, 'code' => strtoupper($name), 'description' => $name.' 的示範工作, 可自由修改；重設 seed 可還原']);
                $run('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => 1, 'statuses' => [...$project['statuses'], ['id' => 'review', 'label' => '待驗收', 'category' => 'active', 'color' => '#8757b3']]]);
                $create = fn ($title, $fields = []) => $run('createIssue', ['projectId' => $project['id'], 'title' => $title, 'assignee' => $user->id, ...$fields]);
                $epic = $create($index ? '正式上線準備' : '會員功能第一版', ['type' => 'epic']);
                $parent = $create($index ? '完成上線前檢查' : '完成登入功能', ['epicId' => $epic['id'], 'estimateMinutes' => 60, 'state' => 'in_progress']);
                $api = $create($index ? '檢查 API 健康狀態' : '實作登入 API', ['parentIssueId' => $parent['id'], 'estimateMinutes' => 120, 'state' => 'review']);
                $ui = $create($index ? '確認手機畫面' : '完成登入畫面', ['parentIssueId' => $parent['id'], 'estimateMinutes' => 90, 'state' => 'todo']);
                $bug = $create($index ? '修正通知文案' : '修正表單錯誤提示', ['type' => 'bug', 'epicId' => $epic['id'], 'estimateMinutes' => 30, 'priority' => 'high', 'relatedIssueIds' => [$parent['id']], 'dueDate' => now('Asia/Taipei')->subDay()->toDateString()]);
                $create($index ? '整理發布說明' : '整理操作說明', ['type' => 'feature', 'state' => 'done', 'epicId' => $epic['id'], 'estimateMinutes' => 60]);
                $create('討論下一次迭代範圍', ['state' => 'backlog', 'assignee' => '']);
                foreach ([[$parent, 30, 2], [$api, 90, 1], [$ui, 45, 0]] as [$issue, $minutes, $daysAgo]) {
                    $run('logTime', ['issueId' => $issue['id'], 'workDate' => now('Asia/Taipei')->subDays($daysAgo)->toDateString(), 'minutes' => $minutes, 'note' => 'Seed 示範工時']);
                }
                $latest = $tenant->require('issues', $bug['id']);
                $run('addComment', ['id' => $bug['id'], 'expectedRevision' => $latest['revision'], 'content' => '已補上重現步驟, 請協助確認']);
            }
            $store->put('demoSeeds', self::WORKSPACE_ID, ['id' => self::WORKSPACE_ID, 'version' => 1, 'seededAt' => now()->utc()->toISOString()]);
            return ['created' => true, 'workspaceId' => self::WORKSPACE_ID];
        });
    }
}
