<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;

/** 升級既有空間角色與明確Seed授權，不擴張一般成員權限。 */
class ProjectAclUpgrade
{
    /**
     * 以指定儲存模式升級資料；正常業務預設使用關聯表。
     * @param bool $legacy 是否只操作待匯入的舊records
     * @return void
     */
    public static function run(bool $legacy = false): void
    {
        DB::transaction(function () use ($legacy) {
            $store = new RecordStore(legacy: $legacy);
            foreach ($store->all('workspaces') as $workspace) {
                $tenant = $store->forWorkspace($workspace['id']);
                if ($tenant->get('upgrades', 'project-acl-v1')) continue;
                $changes = [];
                foreach ($tenant->all('members') as $member) {
                    $role = match ($member['role'] ?? '') { 'admin' => 'manager', 'member', 'maintainer', 'developer' => 'user', default => $member['role'] ?? '' };
                    if ($role !== ($member['role'] ?? '')) {
                        $changes[] = ['userId' => $member['id'], 'before' => $member['role'], 'after' => $role];
                        $tenant->put('members', $member['id'], [...$member, 'role' => $role]);
                    }
                }
                // 只有明確的測試 namespace／穩定 ID 作對照授權, 不推測其他成員資格
                if ($workspace['id'] === DemoData::WORKSPACE_ID && $store->get('demoSeeds', DemoData::WORKSPACE_ID)) self::seedAssignments($tenant);
                $tenant->put('upgrades', 'project-acl-v1', ['id' => 'project-acl-v1', 'at' => now()->utc()->toISOString(), 'actor' => '系統：Project ACL 升級', 'roleChanges' => $changes, 'note' => '未自動加入既有一般成員；業務資料與歷史保留']);
            }
        });
    }

    public static function seedAssignments(RecordStore $tenant): void
    {
        foreach ($tenant->all('projects') as $project) {
            foreach (['maintainer', 'developer'] as $role) {
                if ($role === 'developer' && ($project['code'] ?? '') !== 'FIRST') continue;
                if (!in_array($project['code'] ?? '', ['FIRST', 'SECOND'], true)) continue;
                $userId = DemoData::ACCOUNTS[$role]['id'];
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
}
