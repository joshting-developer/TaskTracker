<?php

namespace Database\Seeders;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Domain\DomainError;
use App\Models\User;
use App\Repositories\RecordStore;
use App\Services\IssueService;
use App\Services\ProjectAclUpgrade;
use App\Services\ProjectService;
use App\Services\WorkspaceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 集中本機／測試環境的帳號、專案、卡片、權限與工時示範資料。 */
class DemoSeeder extends Seeder
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

    /** Laravel db:seed入口，在任何讀寫前驗證環境。
     * @return void
     */
    public function run(): void
    {
        $this->assertEnvironment();
        $this->seed();
    }

    /** 拒絕local／testing以外環境，不允許--force略過。
     * @return void
     */
    public function assertEnvironment(): void
    {
        if (! app()->environment('local', 'testing')) {
            DomainError::fail('FORBIDDEN', 'DemoSeeder 僅供 local／testing 環境');
        }
    }

    /** 建立或補齊指定示範namespace，不覆寫現有使用者資料。
     * @param bool $reset 是否精確重設seed工作區
     * @param ?string $password 初次建立的本機測試密碼
     * @return array 建立／升級結果與workspaceId
     */
    public function seed(bool $reset = false, ?string $password = null): array
    {
        $this->assertEnvironment();
        return DB::transaction(function () use ($reset, $password) {
            $store = new RecordStore;
            $marker = $store->get('demoSeeds', self::WORKSPACE_ID);
            if (($marker['version'] ?? 0) >= 2 && ! $reset) {
                ProjectAclUpgrade::run();
                $workspace = $store->require('workspaces', self::WORKSPACE_ID);
                $upgraded = $this->seedAnalysisTimes($store->forWorkspace(self::WORKSPACE_ID), $workspace);
                return ['created' => false, 'upgraded' => $upgraded, 'workspaceId' => self::WORKSPACE_ID];
            }
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
                $this->seedAssignments($tenant);
                ProjectAclUpgrade::run();
                $store->put('demoSeeds', self::WORKSPACE_ID, [...$marker, 'version' => 2, 'upgradedAt' => now()->utc()->toISOString()]);
                return ['created' => false, 'upgraded' => true, 'workspaceId' => self::WORKSPACE_ID];
            }
            $service = $services['owner'];
            $run = fn ($op, $input) => $service->execute($op, [...$input, 'requestId' => (string) Str::uuid()]);
            foreach (['first', 'second'] as $index => $name) {
                $project = $run('createProject', ['name' => $name, 'code' => strtoupper($name), 'description' => $name.' 的示範工作, 可自由修改；重設 seed 可還原']);
                $run('updateStatuses', ['projectId' => $project['id'], 'expectedRevision' => 1, 'statuses' => [...$project['statuses'], ['id' => 'review', 'label' => '待驗收', 'color' => '#8757b3']]]);
                $this->seedAssignments($tenant);
                // 非開發者成員不能在 second 被指派新工作
                $create = fn ($title, $fields = []) => $run('createIssue', ['projectId' => $project['id'], 'title' => $title, 'assignee' => self::ACCOUNTS[$index ? 'maintainer' : 'developer']['id'], ...$fields]);
                $epic = $create($index ? '正式上線準備' : '會員功能第一版', ['type' => 'epic', 'assignee' => self::ACCOUNTS['manager']['id']]);
                $parent = $create($index ? '完成上線前檢查' : '完成登入功能', ['epicId' => $epic['id'], 'estimateMinutes' => 60, 'state' => 'inprogress', 'assignee' => self::ACCOUNTS['maintainer']['id']]);
                $api = $create($index ? '檢查 API 健康狀態' : '實作登入 API', ['parentIssueId' => $parent['id'], 'estimateMinutes' => 120, 'state' => 'review']);
                $ui = $create($index ? '確認手機畫面' : '完成登入畫面', ['parentIssueId' => $parent['id'], 'estimateMinutes' => 90, 'state' => 'open']);
                $bug = $create($index ? '修正通知文案' : '修正表單錯誤提示', ['type' => 'bug', 'epicId' => $epic['id'], 'estimateMinutes' => 30, 'priority' => 'high', 'relatedIssueIds' => [$parent['id']], 'dueDate' => now('Asia/Taipei')->subDay()->toDateString()]);
                $create($index ? '整理發布說明' : '整理操作說明', ['type' => 'task', 'state' => 'closed', 'epicId' => $epic['id'], 'estimateMinutes' => 60]);
                $create('討論下一次迭代範圍', ['state' => 'open', 'assignee' => '']);
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
            $this->seedAnalysisTimes($tenant, $workspace);
            $store->put('demoSeeds', self::WORKSPACE_ID, ['id' => self::WORKSPACE_ID, 'version' => 2, 'seededAt' => now()->utc()->toISOString()]);
            return ['created' => true, 'upgraded' => false, 'workspaceId' => self::WORKSPACE_ID];
        });
    }

    /** 由seed既有交易呼叫，按月冪等補最近28天，保留使用者修改。
     * @param RecordStore $tenant 已限定seed工作區的資料入口
     * @param array $workspace seed工作區
     * @return bool 是否新增示範工時
     */
    private function seedAnalysisTimes(RecordStore $tenant, array $workspace): bool
    {
        $this->assertEnvironment();
        if ($workspace['id'] !== self::WORKSPACE_ID) {
            throw new \RuntimeException('工時示範僅限 seed 工作區');
        }
        $today = CarbonImmutable::today('Asia/Taipei');
        $markerId = 'demo-time-analysis-v1-'.$today->format('Y-m');
        if ($tenant->get('upgrades', $markerId)) {
            return false;
        }
        $projects = [];
        foreach ($tenant->all('projects') as $project) {
            if (in_array($project['code'], ['FIRST', 'SECOND'], true)) {
                $projects[$project['code']] = $project;
            }
        }
        if (count($projects) !== 2) {
            return false;
        }
        $issues = $tenant->all('issues');
        $daily = [];
        foreach ($tenant->all('timeEntries') as $entry) {
            if (empty($entry['voidedAt'])) {
                $key = $entry['userId'].':'.$entry['workDate'];
                $daily[$key] = ($daily[$key] ?? 0) + $entry['minutes'];
            }
        }
        $count = 0;
        $seededDates = array_column($tenant->all('upgrades'), null, 'id');
        $newDates = [];
        foreach (self::ACCOUNTS as $role => $account) {
            $member = $tenant->get('members', $account['id']);
            if (! $member || ! empty($member['disabledAt'])) {
                continue;
            }
            $session = [
                'user' => ['id' => $member['id'], 'name' => $member['name']],
                'workspace' => $workspace,
                'permissions' => WorkspacePermissions::resolve($member['role']),
            ];
            $access = new ProjectAccess($tenant, $session);
            $service = new WorkspaceService($session);
            for ($daysAgo = 27; $daysAgo >= 0; $daysAgo--) {
                $date = $today->subDays($daysAgo);
                // 週日不預置資料；空白日期與起伏刻意保留作分析示範。
                $dateMarker = 'demo-time-analysis-day-v1-'.$date->toDateString();
                if ($date->dayOfWeekIso === 7 || isset($seededDates[$dateMarker])) {
                    continue;
                }
                foreach ($projects as $code => $project) {
                    if (! $access->can($project['id'], 'timeEntry.writeOwn')) {
                        continue;
                    }
                    $pool = array_values(array_filter($issues, fn ($issue) => $issue['projectId'] === $project['id']));
                    if (! $pool) {
                        continue;
                    }
                    $baseMinutes = match ($role) {
                        'owner' => $code === 'FIRST' ? 30 : 45,
                        'manager' => $code === 'FIRST' ? 60 : 90,
                        'maintainer' => $code === 'FIRST' ? 120 : 60,
                        'developer' => $code === 'FIRST' ? 210 : 30,
                    };
                    $minutes = $baseMinutes + ($daysAgo % 4) * 15;
                    $key = $member['id'].':'.$date->toDateString();
                    if (($daily[$key] ?? 0) + $minutes > 1440) {
                        continue;
                    }
                    // 部分紀錄直接填Epic，其餘落在Task／Bug；報表各筆只算一次。
                    $issue = $pool[($daysAgo + strlen($role)) % count($pool)];
                    $service->execute('logTime', [
                        'requestId' => (string) Str::uuid(),
                        'issueId' => $issue['id'],
                        'workDate' => $date->toDateString(),
                        'minutes' => $minutes,
                        'note' => '工時分析示範：'.$role.'／'.$code,
                    ]);
                    $daily[$key] = ($daily[$key] ?? 0) + $minutes;
                    $count++;
                    $newDates[$dateMarker] = true;
                }
            }
        }
        if ($count === 0) {
            return false;
        }
        foreach (array_keys($newDates) as $dateMarker) {
            $tenant->put('upgrades', $dateMarker, [
                'id' => $dateMarker,
                'at' => now()->utc()->toISOString(),
                'actor' => '系統：工時分析示範',
                'note' => '此工作日已建立示範，後續月份不重複補入',
            ]);
        }
        $tenant->put('upgrades', $markerId, [
            'id' => $markerId,
            'at' => now()->utc()->toISOString(),
            'actor' => '系統：工時分析示範',
            'note' => '已新增 '.$count.' 筆；重跑不修改或補回本月示範紀錄',
        ]);

        return true;
    }

    /** 為明確seed帳號加入示範專案資格，不改一般成員規則。
     * @param RecordStore $tenant 示範工作區資料入口
     * @return void
     */
    private function seedAssignments(RecordStore $tenant): void
    {
        foreach ($tenant->all('projects') as $project) {
            foreach (['maintainer', 'developer'] as $role) {
                if ($role === 'developer' && ($project['code'] ?? '') !== 'FIRST') continue;
                if (!in_array($project['code'] ?? '', ['FIRST', 'SECOND'], true)) continue;
                $userId = self::ACCOUNTS[$role]['id'];
                $member = $tenant->get('members', $userId);
                if (!$member || !empty($member['disabledAt'])) continue;
                $tenant->put('members', $userId, [...$member, 'role' => 'user']);
                $id = ProjectAccess::memberId($project['id'], $userId);
                if ($tenant->get('projectMembers', $id)) continue;
                $tenant->put('projectMembers', $id, ['id' => $id, 'projectId' => $project['id'], 'userId' => $userId, 'roleId' => $role, 'revision' => 1, 'removedAt' => null, 'updatedAt' => now()->utc()->toISOString()]);
                $acl = $tenant->get('projectAcls', $project['id']) ?? ['id' => $project['id'], 'revision' => 0, 'history' => []];
                $tenant->put('projectAcls', $project['id'], [...$acl, 'revision' => $acl['revision'] + 1, 'history' => [...$acl['history'], ['actorId' => 'system', 'actor' => '系統：Seed ACL', 'userId' => $userId, 'before' => null, 'after' => $role, 'at' => now()->utc()->toISOString()]]]);
            }
        }
    }

    /**
     * 建立單一示範專案及其預設任務。
     *
     * @param array $workspaceSession 後端重新驗證的工作區身分
     * @return array
     */
    public function loadProject(array $workspaceSession): array
    {
        $this->assertEnvironment();
        WorkspacePermissions::authorize($workspaceSession, 'project.create');
        $store = (new RecordStore)->forWorkspace($workspaceSession['workspace']['id']);
        $projects = new ProjectService($store, $workspaceSession);
        $issues = new IssueService($store, $workspaceSession);
        if (in_array('DEMO', array_column($store->all('projects'), 'code'), true)) {
            DomainError::fail('DEMO_EXISTS', '示範專案已存在, 不會重複載入');
        }
        $project = $projects->createProject([
            'name' => '產品第一版 · 示範',
            'code' => 'DEMO',
            'description' => '用這個示範專案試試清單、看板與任務修改, 內容不代表正式需求',
        ]);
        $rows = [
            ['整理第一版需求與使用流程', 'closed', 'high', 'task'],
            ['建立專案與任務資料結構', 'closed', 'normal', 'task'],
            ['設計任務清單的搜尋與篩選', 'inprogress', 'high', 'task'],
            ['讓看板拖曳與任務狀態同步', 'inprogress', 'normal', 'task'],
            ['手機上的表單需要更好操作', 'open', 'high', 'bug'],
            ['補上任務留言與異動紀錄', 'open', 'normal', 'task'],
            ['確認 Laravel API 部署方式', 'open', 'normal', 'task'],
            ['討論下一次迭代範圍', 'open', 'low', 'task'],
            ['研究適合團隊的登入流程', 'open', 'normal', 'task'],
        ];
        foreach ($rows as $index => [$title, $state, $priority, $type]) {
            $issues->createIssue([
                'projectId' => $project['id'],
                'title' => $title,
                'state' => $state,
                'priority' => $priority,
                'type' => $type,
                'assignee' => in_array($index, [4, 6, 7, 8], true) ? '' : $workspaceSession['user']['id'],
            ]);
        }

        return ['projectId' => $project['id']];
    }
}
