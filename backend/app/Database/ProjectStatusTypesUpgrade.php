<?php

namespace App\Database;

use App\Domain\Fields;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;

/** 補齊既有專案的保留狀態，沿用狀態識別碼，不改任務與歷史內容。 */
class ProjectStatusTypesUpgrade
{
    /** 於migration或舊版匯入後補齊open／closed，保留設定版本衝突保護。
     * @return void
     */
    public static function run(): void
    {
        DB::transaction(function () {
            $store = new RecordStore;
            foreach ($store->all('workspaces') as $workspace) {
                $tenant = $store->forWorkspace($workspace['id']);
                foreach ($tenant->all('projects') as $project) {
                    $states = $project['statuses'];
                    $before = $states;
                    foreach (Fields::STATES as $required) {
                        if (! in_array($required['id'], ['open', 'closed'], true) || in_array($required['id'], array_column($states, 'id'), true)) {
                            continue;
                        }
                        // 顯示名稱可重複於歷史資料；新增保留狀態時避免與舊名稱衝突。
                        if (in_array($required['label'], array_column($states, 'label'), true)) {
                            $required['label'] .= ' ('.$required['id'].')';
                        }
                        $states[] = $required;
                    }
                    if ($states === $before) {
                        continue;
                    }
                    $project['statuses'] = $states;
                    $project['revision']++;
                    $project['settingsHistory'][] = [
                        'actor' => '系統：補齊 open／closed 狀態',
                        'at' => now()->utc()->toISOString(),
                        'before' => $before,
                        'after' => $states,
                    ];
                    $tenant->put('projects', $project['id'], $project);
                }
            }
        });
    }
}
