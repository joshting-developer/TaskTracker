<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Repositories\RecordStore;
use Illuminate\Support\Str;

/** 將舊前置依賴轉為雙向關聯，供歷史migration及匯入共用。 */
class IssueRelationsUpgrade
{
    // 供 Laravel migration 與之後的舊版匯入共用；保留原 blockedBy 在歷史中
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
                $issues = array_column($tenant->all('issues'), null, 'id');
                $links = [];
                foreach ($issues as $id => $issue) {
                    $links[$id] = $issue['relatedIssueIds'] ?? [];
                }
                foreach ($issues as $id => $issue) {
                    foreach ($issue['blockedBy'] ?? [] as $targetId) {
                        $target = $issues[$targetId] ?? null;
                        if (!$target || $id === $targetId || $target['projectId'] !== $issue['projectId']) continue;
                        $links[$id][] = $targetId;
                        $links[$targetId][] = $id;
                    }
                }
                foreach ($issues as $id => $issue) {
                    $related = array_values(array_unique($links[$id]));
                    sort($related);
                    if (!array_key_exists('blockedBy', $issue) && array_key_exists('parentIssueId', $issue) && ($issue['relatedIssueIds'] ?? null) === $related) continue;
                    $at = now()->utc()->toISOString();
                    $changes = [['field' => 'relatedIssueIds', 'before' => $issue['relatedIssueIds'] ?? [], 'after' => $related]];
                    if (array_key_exists('blockedBy', $issue)) $changes[] = ['field' => 'blockedBy', 'before' => $issue['blockedBy'], 'after' => []];
                    unset($issue['blockedBy']);
                    $issue = [...$issue, 'parentIssueId' => $issue['parentIssueId'] ?? '', 'relatedIssueIds' => $related, 'revision' => ($issue['revision'] ?? 0) + 1, 'updatedAt' => $at, 'history' => [...($issue['history'] ?? []), ['id' => (string) Str::uuid(), 'kind' => 'migrated', 'actor' => '系統：改用子任務與關聯任務', 'at' => $at, 'changes' => $changes]]];
                    $tenant->put('issues', $id, $issue);
                }
            }
        });
    }
}
