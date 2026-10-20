<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueTypesUpgrade
{
    // 僅升級舊 Feature, migration 與舊版匯入共用, 不重設其他任務資料
    public static function run(): void
    {
        DB::transaction(function () {
            $kinds = DB::table('records')->where('kind', 'like', 'w:%:issues')->distinct()->pluck('kind');
            foreach ($kinds as $kind) {
                foreach (DB::table('records')->where('kind', $kind)->get() as $row) {
                    $issue = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
                    if (($issue['type'] ?? '') !== 'feature') continue;
                    $at = now()->utc()->toISOString();
                    $issue = [...$issue, 'type' => 'task', 'revision' => ($issue['revision'] ?? 0) + 1, 'updatedAt' => $at,
                        'history' => [...($issue['history'] ?? []), ['id' => (string) Str::uuid(), 'kind' => 'migrated', 'actor' => '系統：類型收斂為 Epic／Task／Bug', 'at' => $at,
                            'changes' => [['field' => 'type', 'before' => 'feature', 'after' => 'task']]]]];
                    DB::table('records')->where('kind', $kind)->where('id', $row->id)->update(['payload' => json_encode($issue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
            }
        });
    }
}
