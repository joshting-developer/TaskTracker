<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueRelationsUpgrade
{
    // 供 Laravel migration 與之後的舊版匯入共用；保留原 blockedBy 在歷史中
    public static function run(): void
    {
        DB::transaction(function () {
            $kinds = DB::table('records')->where('kind', 'like', 'w:%:issues')->distinct()->pluck('kind');
            foreach ($kinds as $kind) {
                $issues = DB::table('records')->where('kind', $kind)->get()->mapWithKeys(fn ($row) => [$row->id => json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR)])->all();
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
                    DB::table('records')->where('kind', $kind)->where('id', $id)->update(['payload' => json_encode($issue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
            }
        });
    }
}
