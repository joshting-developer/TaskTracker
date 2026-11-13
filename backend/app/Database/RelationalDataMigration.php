<?php

namespace App\Database;

use App\Repositories\RelationalRecords;

use App\Repositories\EntityDefinitions;
use App\Repositories\LegacyRecordStore;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 依依賴順序把歷史JSON回填到關聯表；來源保留，錯誤時整批回滾。 */
class RelationalDataMigration
{
    /** 升級空目標，原始records作歷史備份不再由正常業務寫入。
     * @return void
     */
    public static function import(): void
    {
        RelationalRecords::$skipColumns = self::missingLaterColumns();
        try {
            self::importRecords();
        } finally {
            RelationalRecords::$skipColumns = [];
        }
    }

    /** 實際匯入；欄位只用匯入當下已存在的（見RelationalRecords::LATER_COLUMNS）。
     * @return void
     */
    private static function importRecords(): void
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
     * 後來才加的欄位若已先被後續migration撤掉, 保留備份裡原本的值, 不用預設值蓋掉（TT-314）。
     * @return void
     */
    public static function export(): void
    {
        $legacy = new LegacyRecordStore;
        $store = new RecordStore;
        $missing = self::missingLaterColumns();
        RelationalRecords::$skipColumns = $missing;
        try {
            foreach (EntityDefinitions::ENTITIES as $kind => $definition) {
                $keep = array_map(fn ($field) => $field[0], array_filter($definition['fields'], fn ($field) => in_array($field[1], $missing[$kind] ?? [], true)));
                if (in_array($definition['scope'], [null, 'global'], true)) {
                    self::exportKind($legacy, $kind, $kind, $store->all($kind), $keep);
                } else {
                    foreach ($store->all('workspaces') as $workspace) {
                        self::exportKind($legacy->forWorkspace($workspace['id']), 'w:'.$workspace['id'].':'.$kind, $kind, $store->forWorkspace($workspace['id'])->all($kind), $keep);
                    }
                }
            }
        } finally {
            RelationalRecords::$skipColumns = [];
        }
    }

    /** 把一種資料整批寫回備份；$keep列的欄位沿用備份舊值。
     * @param  LegacyRecordStore  $legacy  目標備份（已切到工作空間）
     * @param  string  $recordKind  records表上的完整kind
     * @param  string  $kind  實體種類
     * @param  array<int, array>  $values  目前資料
     * @param  array<int, string>  $keep  表上已不存在、要沿用舊值的欄位
     * @return void
     */
    private static function exportKind(LegacyRecordStore $legacy, string $recordKind, string $kind, array $values, array $keep): void
    {
        $previous = [];
        if ($keep !== []) {
            foreach ($legacy->all($kind) as $old) {
                $previous[$old['id']] = array_intersect_key($old, array_flip($keep));
            }
        }
        DB::table('records')->where('kind', $recordKind)->delete();
        foreach ($values as $value) {
            $legacy->put($kind, $value['id'], [...$value, ...($previous[$value['id']] ?? [])]);
        }
    }

    /** 每種資料表上還沒有（或已撤掉）的後加欄位。
     * @return array<string, array<int, string>>
     */
    private static function missingLaterColumns(): array
    {
        $missing = [];
        foreach (RelationalRecords::LATER_COLUMNS as $kind => $columns) {
            $table = EntityDefinitions::ENTITIES[$kind]['table'];
            $missing[$kind] = array_values(array_filter($columns, fn ($column) => ! Schema::hasColumn($table, $column)));
        }

        return array_filter($missing);
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
