<?php

namespace App\Queries;

use Illuminate\Database\Eloquent\Builder;

/** 組合明確收錄或收錄Epic所屬卡片的SQL條件，不載入ID全集。 */
class BoardIssueScope
{
    /**
     * 將看板收錄交集套入已限定Project的任務查詢。
     *
     * @param  Builder  $issues  已限定工作區與Project的查詢
     * @param  int|null  $boardId  看板內部ID；null僅用於boards外層的相關子查詢
     * @return Builder 仍待執行的同Project查詢
     */
    public function apply(Builder $issues, ?int $boardId): Builder
    {
        $board = fn ($q) => $boardId === null ? $q->whereColumn('selection.board_id', 'boards.id') : $q->where('selection.board_id', $boardId);

        return $issues->where(function ($q) use ($board) {
            $q->whereExists(function ($q) use ($board) {
                $q->selectRaw('1')->from('board_issues as selection')
                    ->whereColumn('selection.issue_id', 'issues.id')
                    ->whereColumn('selection.project_id', 'issues.project_id')
                    ->whereColumn('selection.workspace_id', 'issues.workspace_id');
                $board($q);
            })->orWhereExists(function ($q) use ($board) {
                $q->selectRaw('1')->from('board_issues as selection')
                    ->join('issues as selected_epic', 'selected_epic.id', '=', 'selection.issue_id')
                    ->where('selected_epic.type', 'epic')->whereColumn('selected_epic.id', 'issues.epic_id')
                    ->whereColumn('selection.project_id', 'issues.project_id')
                    ->whereColumn('selection.workspace_id', 'issues.workspace_id');
                $board($q);
            });
        });
    }
}
