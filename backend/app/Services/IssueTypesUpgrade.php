<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Repositories\RecordStore;
use Illuminate\Support\Str;

/** 將歷史Feature收斂為Task，保留任務欄位與異動歷史。 */
class IssueTypesUpgrade
{
    // 僅升級舊 Feature, migration 與舊版匯入共用, 不重設其他任務資料
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
                foreach ($tenant->all('issues') as $issue) {
                    if (($issue['type'] ?? '') !== 'feature') continue;
                    $at = now()->utc()->toISOString();
                    $issue = [...$issue, 'type' => 'task', 'revision' => ($issue['revision'] ?? 0) + 1, 'updatedAt' => $at,
                        'history' => [...($issue['history'] ?? []), ['id' => (string) Str::uuid(), 'kind' => 'migrated', 'actor' => '系統：類型收斂為 Epic／Task／Bug', 'at' => $at,
                            'changes' => [['field' => 'type', 'before' => 'feature', 'after' => 'task']]]]];
                    $tenant->put('issues', $issue['id'], $issue);
                }
            }
        });
    }
}
