<?php

namespace Tests\Feature;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Factories\WorkspaceServicesFactory;
use App\Repositories\RecordStore;
use App\Services\WorkspaceQuery;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** 驗證批次唯讀資格與逐筆授權等價，且工時／成員增加不放大SQL。 */
class ReadPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** 量測一次完整唯讀用例，query log只存在測試程序。 */
    private function counted(WorkspaceQuery $query): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $result = $query->getWorkspace();

            return [$result, count(DB::getQueryLog())];
        } finally {
            DB::disableQueryLog();
        }
    }

    /** 四帳號、缺失專案、停用及撤權，使用相同permission映射，不跨請求保存快照。 */
    public function test_read_permissions_match_live_checks_and_rebuild_after_revocation(): void
    {
        app(DemoSeeder::class)->seed(password: 'read-performance-test');
        $store = (new RecordStore)->forWorkspace(DemoSeeder::WORKSPACE_ID);
        $projects = $store->all('projects');
        foreach (DemoSeeder::ACCOUNTS as $role => $account) {
            $context = ['user' => ['id' => $account['id']], 'workspace' => ['id' => DemoSeeder::WORKSPACE_ID], 'role' => $role, 'permissions' => WorkspacePermissions::resolve($role)];
            $live = new ProjectAccess($store, $context);
            $snapshot = ProjectAccess::forRead($store, $context, $projects, $store->all('members'));
            foreach ([...array_column($projects, 'id'), 'missing'] as $id) {
                $this->assertSame($live->permissions($id), $snapshot->permissions($id));
                $this->assertSame($live->eligibleMembers($id), $snapshot->eligibleMembers($id));
            }
        }
        $id = DemoSeeder::ACCOUNTS['developer']['id'];
        $context = ['user' => ['id' => $id, 'name' => 'test member'], 'workspace' => ['id' => DemoSeeder::WORKSPACE_ID, 'name' => 'f-ws'], 'role' => 'user', 'permissions' => WorkspacePermissions::resolve('user')];
        $query = app(WorkspaceServicesFactory::class)->forSession($context)->query();
        $before = $query->getWorkspace();
        $this->assertCount(1, $before['projects']);
        $projectId = $before['projects'][0]['id'];
        $assignment = $store->require('projectMembers', ProjectAccess::memberId($projectId, $id));
        $store->put('projectMembers', $assignment['id'], [...$assignment, 'removedAt' => now()->toISOString()]);
        $after = $query->getWorkspace();
        $this->assertSame([], $after['projects']);
        $this->assertSame([], $after['issues']);
        $this->assertSame([], $after['timeEntries']);
        $store->put('projectMembers', $assignment['id'], $assignment);
        $member = $store->require('members', $id);
        $store->put('members', $id, [...$member, 'disabledAt' => now()->toISOString()]);
        $this->assertSame([], $query->getWorkspace()['projects']);
        $this->assertSame([], (new ProjectAccess($store, $context))->permissions($projectId), '寫入所用即時資格仍看得到停用');
    }

    /** 增200筆工時仍回完整紀錄，但SQL數不隨entry成長。 */
    public function test_workspace_queries_do_not_grow_per_time_entry_for_owner_and_developer(): void
    {
        app(DemoSeeder::class)->seed(password: 'read-performance-test');
        $store = (new RecordStore)->forWorkspace(DemoSeeder::WORKSPACE_ID);
        foreach (['owner', 'developer'] as $role) {
            $id = DemoSeeder::ACCOUNTS[$role]['id'];
            $context = ['user' => ['id' => $id, 'name' => 'test member'], 'workspace' => ['id' => DemoSeeder::WORKSPACE_ID, 'name' => 'f-ws'], 'role' => $role, 'permissions' => WorkspacePermissions::resolve($role)];
            $query = app(WorkspaceServicesFactory::class)->forSession($context)->query();
            [$before, $beforeCount] = $this->counted($query);
            $source = (array) DB::table('time_entries')->where('member_id', DB::table('workspace_members')->where('user_id', $id)->value('id'))->first();
            unset($source['id']);
            for ($index = 0; $index < 200; $index++) {
                DB::table('time_entries')->insert([...$source, 'public_id' => $role.'-performance-'.$index]);
            }
            [$after, $afterCount] = $this->counted($query);
            $this->assertCount(count($before['timeEntries']) + 200, $after['timeEntries']);
            $this->assertSame($before['projects'], $after['projects']);
            $this->assertSame($before['issues'], $after['issues']);
            $this->assertLessThan(100, $afterCount, '小型workspace不應需要數千條SQL');
            $this->assertLessThanOrEqual($beforeCount + 2, $afterCount, '不可每entry追加授權SQL');
        }
    }
}
