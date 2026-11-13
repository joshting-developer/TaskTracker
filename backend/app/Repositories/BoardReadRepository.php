<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Models\Board;
use App\Models\Issue;
use App\Models\Project;
use App\Queries\BoardIssueScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
     * 取得自訂看板分頁；每列卡數與結案數在同SQL聚合，不載入卡片內容。
     *
     * @param  Project  $project  已授權Project
     * @param  PageQueryData  $filters  有界搜尋與分頁
     * @param  bool  $archived  true時只列已封存、依封存時間新到舊；false只列使用中
     * @return LengthAwarePaginator<Board> 看板摘要
     */
    public function page(Project $project, PageQueryData $filters, bool $archived = false): LengthAwarePaginator
    {
        $count = Issue::whereColumn('issues.project_id', 'boards.project_id')->whereColumn('issues.workspace_id', 'boards.workspace_id');
        $this->scope->apply($count, null);
        $closed = $this->closed(clone $count);
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';

        // TT-283：使用中依名稱排序；已封存依封存時間新到舊, 兩者都以public_id穩定排序。
        return Board::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->select(['id', 'public_id', 'project_id', 'name', 'description', 'revision', 'archived_at'])->selectSub($count->selectRaw('COUNT(*)'), 'issue_count')
            ->selectSub($closed->selectRaw('COUNT(*)'), 'closed_count')
            ->when($filters->search !== '', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]))
            ->when($archived, fn ($q) => $q->orderByDesc('archived_at'), fn ($q) => $q->orderBy('name'))->orderBy('public_id')->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * 查同Project的看板屬性；TT-283起包含已封存（唯讀查閱）, 寫入由ProjectBoards::current擋下。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $id  看板公開ID
     * @return Board|null 存在時的看板
     */
    public function find(Project $project, string $id): ?Board
    {
        return Board::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->where('public_id', $id)->first(['id', 'public_id', 'project_id', 'name', 'description', 'revision', 'archived_at']);
    }

    /**
     * 計算指定Project或看板的去重卡數與其中已結案數，與收錄／Epic展開同一條件。
     *
     * @param  Project  $project  已授權Project
     * @param  Board|null  $board  null表示Default
     * @return array{issueCount:int,closedCount:int} 符合收錄的卡數與結案數
     */
    public function count(Project $project, ?Board $board): array
    {
        $q = Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id);
        if ($board) {
            $this->scope->apply($q, $board->id);
        }

        return ['issueCount' => (clone $q)->count(), 'closedCount' => $this->closed($q)->count()];
    }

    /**
     * 限定為結案卡：只有狀態type（project_statuses.code）為closed才算，不依顯示名稱。
     *
     * @param  Builder<Issue>  $issues  已套用Project與看板收錄條件的任務查詢
     * @return Builder<Issue> 仍待執行的結案卡查詢
     */
    private function closed(Builder $issues): Builder
    {
        return $issues->whereIn('issues.state_id', fn ($q) => $q->select('project_statuses.id')->from('project_statuses')
            ->whereColumn('project_statuses.project_id', 'issues.project_id')->where('project_statuses.code', 'closed'));
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

    /**
     * 看板畫面相依資料的單調版本來源：只取計數與revision加總，不讀卡片內容。
     * Project內卡片只在整個Workspace重設時刪除，且每次寫入revision+1，故計數＋加總只增不減，不依時間字串。
     * 卡片採整個Project範圍（自訂看板可能多觸發重讀，但不漏）；工時依可見範圍限定，不以總數洩漏他人投入。
     *
     * @param  Project  $project  已授權Project
     * @param  string|null  $timeUser  僅能看本人工時時的User ID
     * @param  bool  $teamTime  是否可看團隊工時
     * @return array<string,int> 版本來源計數
     */
    public function versionParts(Project $project, ?string $timeUser, bool $teamTime): array
    {
        $scope = fn ($q) => $q->where('workspace_id', $project->workspace_id)->where('project_id', $project->id);
        $projectRow = DB::table('projects')->where('workspace_id', $project->workspace_id)->where('id', $project->id)->first(['revision', 'lead_id']);
        $issues = $scope(DB::table('issues'))->selectRaw('COUNT(*) AS total, COALESCE(SUM(revision),0) AS revisions')->first();
        // 卡片承辦者與Project負責人的顯示名稱：改名只增加profile_revision，不動Issue revision。
        $people = DB::table('workspace_members as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.workspace_id', $project->workspace_id)
            ->where(fn ($q) => $q->whereIn('m.id', $scope(DB::table('issues'))->whereNotNull('assignee_id')->select('assignee_id'))->orWhere('m.id', $projectRow->lead_id))
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(u.profile_revision),0) AS revisions')->first();
        $time = ['total' => 0, 'revisions' => 0];
        if ($timeUser !== null || $teamTime) {
            $entries = DB::table('time_entries as t')->where('t.workspace_id', $project->workspace_id)->where('t.project_id', $project->id);
            if (! $teamTime) {
                $entries->whereIn('t.member_id', DB::table('workspace_members')->select('id')->where('workspace_id', $project->workspace_id)->where('user_id', $timeUser));
            }
            $time = (array) $entries->selectRaw('COUNT(*) AS total, COALESCE(SUM(t.revision),0) AS revisions')->first();
        }

        return ['project' => (int) $projectRow->revision, 'issues' => (int) $issues->total, 'issueRevisions' => (int) $issues->revisions,
            'people' => (int) $people->total, 'peopleRevisions' => (int) $people->revisions, 'time' => (int) $time['total'], 'timeRevisions' => (int) $time['revisions']];
    }
}
