<?php

namespace App\Database;

use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;

/** 升級既有空間角色，不擴張一般成員權限；示範授權由DemoSeeder處理。 */
class ProjectAclUpgrade
{
    /**
     * 以指定儲存模式升級資料；正常業務預設使用關聯表。
     *
     * @param  bool  $legacy  是否只操作待匯入的舊records
     * @return void
     */
    public static function run(bool $legacy = false): void
    {
        DB::transaction(function () use ($legacy) {
            $store = new RecordStore(legacy: $legacy);
            foreach ($store->all('workspaces') as $workspace) {
                $tenant = $store->forWorkspace($workspace['id']);
                if ($tenant->get('upgrades', 'project-acl-v1')) {
                    continue;
                }
                $changes = [];
                foreach ($tenant->all('members') as $member) {
                    $role = match ($member['role'] ?? '') {
                        'admin' => 'manager', 'member', 'maintainer', 'developer' => 'user', default => $member['role'] ?? ''
                    };
                    if ($role !== ($member['role'] ?? '')) {
                        $changes[] = ['userId' => $member['id'], 'before' => $member['role'], 'after' => $role];
                        $tenant->put('members', $member['id'], [...$member, 'role' => $role]);
                    }
                }
                $tenant->put('upgrades', 'project-acl-v1', ['id' => 'project-acl-v1', 'at' => now()->utc()->toISOString(), 'actor' => '系統：Project ACL 升級', 'roleChanges' => $changes, 'note' => '未自動加入既有一般成員；業務資料與歷史保留']);
            }
        });
    }
}
