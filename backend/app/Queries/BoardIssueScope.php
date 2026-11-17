<?php

namespace App\Queries;

use App\Data\BoardSelectionData;
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

        $scope = fn ($q, string $alias) => $boardId === null ? $q->whereColumn($alias.'.board_id', 'boards.id') : $q->where($alias.'.board_id', $boardId);

        return $issues->whereNotExists(function ($q) use ($scope) {
            $q->selectRaw('1')->from('board_exclusions as excluded')
                ->whereColumn('excluded.issue_id', 'issues.id')->whereColumn('excluded.project_id', 'issues.project_id')->whereColumn('excluded.workspace_id', 'issues.workspace_id');
            $scope($q, 'excluded');
        })->where(function ($q) use ($board, $scope) {
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
            })->orWhereExists(function ($q) use ($scope) {
                $q->selectRaw('1')->from('board_rules as enabled')->where('enabled.kind', 'enabled')
                    ->whereColumn('enabled.project_id', 'issues.project_id')->whereColumn('enabled.workspace_id', 'issues.workspace_id');
                $scope($q, 'enabled');
                // 任一必要 Tag 不符合就拒絕；刪除定義後保留規則, 不會意外放寬收錄。
                $q->whereNotExists(function ($tags) {
                    $tags->selectRaw('1')->from('board_rules as required_tag')->whereColumn('required_tag.board_id', 'enabled.board_id')->where('required_tag.kind', 'tag')
                        ->whereNotExists(function ($link) {
                            $link->selectRaw('1')->from('issue_tags')->join('project_tags', 'project_tags.id', '=', 'issue_tags.tag_id')
                                ->whereColumn('issue_tags.issue_id', 'issues.id')->whereColumn('issue_tags.project_id', 'issues.project_id')->whereColumn('issue_tags.workspace_id', 'issues.workspace_id')
                                ->whereColumn('project_tags.public_id', 'required_tag.value')->whereNull('project_tags.deleted_at');
                        });
                });
                foreach (['type', 'state'] as $kind) {
                    $q->where(function ($group) use ($kind) {
                        $group->whereNotExists(fn ($rule) => $rule->selectRaw('1')->from('board_rules as available_rule')->whereColumn('available_rule.board_id', 'enabled.board_id')->where('available_rule.kind', $kind))
                            ->orWhereExists(function ($rule) use ($kind) {
                                $rule->selectRaw('1')->from('board_rules as matched_rule')->whereColumn('matched_rule.board_id', 'enabled.board_id')->where('matched_rule.kind', $kind);
                                if ($kind === 'type') {
                                    $rule->whereColumn('matched_rule.value', 'issues.type');
                                } else {
                                    $rule->whereExists(fn ($status) => $status->selectRaw('1')->from('project_statuses')->whereColumn('project_statuses.id', 'issues.state_id')->whereColumn('project_statuses.project_id', 'issues.project_id')->whereColumn('project_statuses.code', 'matched_rule.value'));
                                }
                            });
                    });
                }
            });
        });
    }

    /** 未儲存條件以相同優先順序預覽, 不建立暫存看板或載出 ID 全集
     * @param  Builder  $issues  已限定工作區與 Project 的卡片
     * @param  BoardSelectionData  $selection  已驗證收錄條件
     * @return Builder 尚未執行的有界讀取
     */
    public function preview(Builder $issues, BoardSelectionData $selection): Builder
    {
        return $issues->whereNotIn('issues.public_id', $selection->excludedIssueIds)->where(function ($query) use ($selection) {
            $query->whereIn('issues.public_id', $selection->selectedIssueIds)
                ->orWhereIn('issues.epic_id', function ($epics) use ($selection) {
                    $epics->select('selected_epic.id')->from('issues as selected_epic')->where('selected_epic.type', 'epic')
                        ->whereColumn('selected_epic.project_id', 'issues.project_id')->whereColumn('selected_epic.workspace_id', 'issues.workspace_id')->whereIn('selected_epic.public_id', $selection->selectedIssueIds);
                });
            if ($selection->autoInclude === null) {
                return;
            }
            $query->orWhere(function ($automatic) use ($selection) {
                foreach ($selection->autoInclude->tagIds as $id) {
                    $automatic->whereHas('tags', fn ($tag) => $tag->where('project_tags.public_id', $id)->whereNull('project_tags.deleted_at'));
                }
                if ($selection->autoInclude->types !== []) {
                    $automatic->whereIn('issues.type', $selection->autoInclude->types);
                }
                if ($selection->autoInclude->states !== []) {
                    $automatic->whereHas('status', fn ($state) => $state->whereIn('code', $selection->autoInclude->states));
                }
                // 空的明確自動條件代表此 Project 全部卡片, 仍受外層排除限制。
                if ($selection->autoInclude->tagIds === [] && $selection->autoInclude->types === [] && $selection->autoInclude->states === []) {
                    $automatic->whereRaw('1 = 1');
                }
            });
        });
    }
}
