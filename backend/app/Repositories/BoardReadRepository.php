<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Models\Board;
use App\Models\Issue;
use App\Models\Project;
use App\Queries\BoardIssueScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 以Project為範圍的看板投影及SQL去重數量。 */
class BoardReadRepository
{
    /**
     * 注入只負責SQL收錄條件的協作者。
     *
     * @param  BoardIssueScope  $scope  明確收錄與Epic展開條件
     */
    public function __construct(private BoardIssueScope $scope) {}

    /**
     * 取得自訂看板分頁；每列卡數在同SQL聚合，不載入卡片內容。
     *
     * @param  Project  $project  已授權Project
     * @param  PageQueryData  $filters  有界搜尋與分頁
     * @return LengthAwarePaginator<Board> 看板摘要
     */
    public function page(Project $project, PageQueryData $filters): LengthAwarePaginator
    {
        $count = Issue::whereColumn('issues.project_id', 'boards.project_id')->whereColumn('issues.workspace_id', 'boards.workspace_id');
        $this->scope->apply($count, null);
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';

        return Board::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->whereNull('archived_at')
            ->select(['id', 'public_id', 'project_id', 'name', 'revision'])->selectSub($count->selectRaw('COUNT(*)'), 'issue_count')
            ->when($filters->search !== '', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]))
            ->orderBy('name')->orderBy('public_id')->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * 僅查同Project尚未封存的看板屬性。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $id  看板公開ID
     * @return Board|null 存在時的看板
     */
    public function find(Project $project, string $id): ?Board
    {
        return Board::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->where('public_id', $id)->whereNull('archived_at')->first(['id', 'public_id', 'project_id', 'name', 'revision']);
    }

    /**
     * 計算指定Project或看板的去重卡數。
     *
     * @param  Project  $project  已授權Project
     * @param  Board|null  $board  null表示Default
     * @return int 符合收錄的卡數
     */
    public function count(Project $project, ?Board $board): int
    {
        $q = Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id);
        if ($board) {
            $this->scope->apply($q, $board->id);
        }

        return $q->count();
    }

    /**
     * 僅在編輯收錄時讀取最多1000個明確選取ID。
     *
     * @param  Board  $board  已授權看板
     * @return array<int,string> 明確收錄ID
     */
    public function selectedIds(Board $board): array
    {
        return $board->issues()->limit(1000)->pluck('issues.public_id')->all();
    }
}
