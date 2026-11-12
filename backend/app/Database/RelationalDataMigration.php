<?php

namespace App\Database;

use App\Repositories\EntityDefinitions;
use App\Repositories\LegacyRecordStore;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;

/** 依依賴順序把歷史JSON回填到關聯表；來源保留，錯誤時整批回滾。 */
class RelationalDataMigration
{
    /** 升級空目標，原始records作歷史備份不再由正常業務寫入。
     * @return void
     */
    public static function import(): void
    {
        DB::transaction(function () {
            if (DB::table('workspaces')->exists()) {
                throw new \RuntimeException('關聯式目標已有資料，拒絕覆蓋');
            }
            $legacy = new LegacyRecordStore;
            $store = new RecordStore;
            foreach ($legacy->all('workspaces') as $workspace) {
                $store->put('workspaces', $workspace['id'], $workspace);
            }
            foreach ($legacy->all('workspaces') as $workspace) {
                $source = $legacy->forWorkspace($workspace['id']);
                $target = $store->forWorkspace($workspace['id']);
                foreach (['members', 'projects'] as $kind) {
                    foreach ($source->all($kind) as $value) {
                        if ($kind === 'projects' && ! isset($value['statuses'])) {
                            $value['statuses'] = [
                                ['id' => 'backlog', 'label' => '待規劃', 'color' => '#9a9da9'],
                                ['id' => 'todo', 'label' => '待處理', 'color' => '#7b82e8'],
                                ['id' => 'in_progress', 'label' => '進行中', 'color' => '#e7a443'],
                                ['id' => 'done', 'label' => '已完成', 'color' => '#4aab8a'],
                            ];
                        }
                        $target->put($kind, $value['id'], $value);
                    }
                }
                $issues = array_map(function ($issue) use ($target) {
                    if (empty($issue['identifier'])) {
                        $project = $target->require('projects', $issue['projectId']);
                        $issue['identifier'] = $project['code'].'-'.$issue['id'];
                    }

                    return $issue;
                }, $source->all('issues'));
                $originalOrder = array_column($issues, 'id');
                usort($issues, fn ($first, $second) => self::issueOrder($first) <=> self::issueOrder($second));
                foreach ($issues as $issue) {
                    $target->put('issues', $issue['id'], [
                        ...$issue,
                        'relatedIssueIds' => [],
                    ]);
                }
                foreach ($issues as $issue) {
                    $target->put('issues', $issue['id'], $issue);
                }
                $workspaceId = DB::table('workspaces')->where('public_id', $workspace['id'])->value('id');
                foreach ($originalOrder as $position => $publicId) {
                    DB::table('issues')->where('workspace_id', $workspaceId)->where('public_id', $publicId)->update(['storage_position' => $position + 1]);
                }
                foreach (['projectMembers', 'projectAcls', 'boards', 'timeEntries', 'notifications', 'todos', 'todoNotifications', 'requests', 'todoRequests', 'upgrades', 'savedViews'] as $kind) {
                    foreach ($source->all($kind) as $value) {
                        $target->put($kind, $value['id'], $value);
                    }
                }
            }
            foreach (['invites', 'demoSeeds'] as $kind) {
                foreach ($legacy->all($kind) as $value) {
                    $store->put($kind, $value['id'], $value);
                }
            }
            if (DB::getDriverName() === 'sqlite' && DB::select('PRAGMA foreign_key_check')) {
                throw new \RuntimeException('關聯式資料外鍵驗證失敗');
            }
        });
    }

    /** 先匯出目前最新狀態，再容許migration移除關聯表。
     * @return void
     */
    public static function export(): void
    {
        $legacy = new LegacyRecordStore;
        $store = new RecordStore;
        foreach (EntityDefinitions::ENTITIES as $kind => $definition) {
            if (in_array($definition['scope'], [null, 'global'], true)) {
                DB::table('records')->where('kind', $kind)->delete();
                foreach ($store->all($kind) as $value) {
                    $legacy->put($kind, $value['id'], $value);
                }
            } else {
                foreach ($store->all('workspaces') as $workspace) {
                    DB::table('records')->where('kind', 'w:'.$workspace['id'].':'.$kind)->delete();
                    foreach ($store->forWorkspace($workspace['id'])->all($kind) as $value) {
                        $legacy->forWorkspace($workspace['id'])->put($kind, $value['id'], $value);
                    }
                }
            }
        }
    }

    /** 先建立Epic及父任務，最後建立一層子任務。
     * @param  array  $issue  舊任務
     * @return int
     */
    private static function issueOrder(array $issue): int
    {
        return $issue['type'] === 'epic' ? 0 : (! empty($issue['parentIssueId']) ? 2 : 1);
    }
}
