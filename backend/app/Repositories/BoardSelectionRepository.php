<?php

namespace App\Repositories;

use App\Data\BoardRuleData;
use App\Data\BoardSelectionData;
use App\Models\Board;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Queries\BoardIssueScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** 以已授權 Project 為範圍保存規則及排除, 不改卡片或手動收錄表 */
class BoardSelectionRepository
{
    /** 注入預覽與正式讀取共用的 SQL 收錄條件
     * @param  BoardIssueScope  $scope  收錄與排除條件
     */
    public function __construct(private BoardIssueScope $scope) {}

    /** 既有 RPC 相容邊界以可信公開 ID 解析同租戶 Project
     * @param  string  $workspaceId  交易內重驗的工作區
     * @param  string  $projectId  專案公開 ID
     * @return Project|null 同工作區專案
     */
    public function project(string $workspaceId, string $projectId): ?Project
    {
        return Project::where('public_id', $projectId)->whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))->first();
    }

    /** 取得指定專案內的看板, 不跨租戶查公開 ID
     * @param  Project  $project  已限定專案
     * @param  string  $id  看板公開 ID
     * @return Board|null 同專案看板
     */
    public function board(Project $project, string $id): ?Board
    {
        return Board::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->where('public_id', $id)->first();
    }

    /** 批次核對有界標籤, 已刪除定義不作新規則
     * @param  Project  $project  已限定專案
     * @param  list<string>  $ids  有界公開 ID
     * @return int 符合範圍的有效定義數
     */
    public function tagCount(Project $project, array $ids): int
    {
        return ProjectTag::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->whereNull('deleted_at')->whereIn('public_id', $ids)->count();
    }

    /** 編輯規則時一次取得已選有效 Tag 名稱, 不逐個查詢或讀全專案定義
     * @param  Project  $project  已授權專案
     * @param  list<string>  $ids  最多 20 個規則 Tag ID
     * @return Collection<int,ProjectTag> 有效定義最小投影, 移除的 ID 不自動補入
     */
    public function tags(Project $project, array $ids): Collection
    {
        return $ids === [] ? new Collection : ProjectTag::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->whereNull('deleted_at')->whereIn('public_id', $ids)->orderBy('name')->orderBy('public_id')->get(['public_id', 'name', 'color']);
    }

    /** 只計算指定狀態代碼是否仍屬於此專案
     * @param  Project  $project  已限定專案
     * @param  list<string>  $states  有界代碼
     * @return int 符合的狀態數
     */
    public function stateCount(Project $project, array $states): int
    {
        return DB::table('project_statuses')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->whereIn('code', $states)->count();
    }

    /** 只核對手動加入及排除的卡片 ID, 不讀原稿或活動
     * @param  Project  $project  已限定專案
     * @param  list<string>  $ids  有界公開 ID
     * @return int 同專案卡片數
     */
    public function issueCount(Project $project, array $ids): int
    {
        return Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->whereIn('public_id', $ids)->count();
    }

    /** 取得最多 1000 個手動例外與 54 個規則值, 不讀卡片內容
     * @param  Project  $project  已限定專案
     * @param  Board  $board  已確認同專案看板
     * @return BoardSelectionData 編輯及部分更新所需快照
     */
    public function selection(Project $project, Board $board): BoardSelectionData
    {
        $scope = ['workspace_id' => $project->workspace_id, 'project_id' => $project->id, 'board_id' => $board->id];
        $rows = DB::table('board_rules')->where($scope)->orderBy('kind')->orderBy('value')->limit(54)->get(['kind', 'value']);
        $values = fn (string $kind) => $rows->where('kind', $kind)->pluck('value')->all();
        $rules = $rows->contains('kind', 'enabled') ? new BoardRuleData($values('tag'), $values('type'), $values('state')) : null;
        $selected = $board->issues()->limit(1000)->pluck('issues.public_id')->all();
        $excluded = DB::table('board_exclusions')->join('issues', 'issues.id', '=', 'board_exclusions.issue_id')
            ->where('board_exclusions.workspace_id', $project->workspace_id)->where('board_exclusions.project_id', $project->id)->where('board_id', $board->id)
            ->orderBy('issues.public_id')->limit(1000)->pluck('issues.public_id')->all();

        return new BoardSelectionData($rules, $selected, $excluded);
    }

    /** 規則與排除隨既有 Workspace 交易原子保存, 不另開交易
     * @param  Project  $project  已限定專案
     * @param  Board  $board  同專案看板
     * @param  BoardSelectionData  $selection  已驗證的有界條件
     * @return void
     */
    public function save(Project $project, Board $board, BoardSelectionData $selection): void
    {
        $scope = ['workspace_id' => $project->workspace_id, 'project_id' => $project->id, 'board_id' => $board->id];
        $rows = [];
        if ($selection->autoInclude !== null) {
            $rows[] = [...$scope, 'rule_key' => 'enabled', 'kind' => 'enabled', 'value' => '1'];
            foreach (['tag' => $selection->autoInclude->tagIds, 'type' => $selection->autoInclude->types, 'state' => $selection->autoInclude->states] as $kind => $values) {
                foreach ($values as $value) {
                    $rows[] = [...$scope, 'rule_key' => $kind.':'.$value, 'kind' => $kind, 'value' => $value];
                }
            }
        }
        DB::table('board_rules')->where($scope)->whereNotIn('rule_key', array_column($rows, 'rule_key'))->delete();
        if ($rows !== []) {
            DB::table('board_rules')->upsert($rows, ['board_id', 'rule_key'], ['kind', 'value']);
        }

        $ids = Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->whereIn('public_id', $selection->excludedIssueIds)->pluck('id')->all();
        $existing = DB::table('board_exclusions')->where($scope)->pluck('issue_id')->all();
        DB::table('board_exclusions')->where($scope)->whereNotIn('issue_id', $ids)->delete();
        foreach (array_chunk(array_values(array_diff($ids, $existing)), 100) as $batch) {
            DB::table('board_exclusions')->insert(array_map(fn ($id) => [...$scope, 'issue_id' => $id], $batch));
        }
    }

    /** 預覽只投影首 20 張卡片, 總數由相同 SQL 條件計算
     * @param  Project  $project  已授權專案
     * @param  BoardSelectionData  $selection  已驗證收錄條件
     * @return LengthAwarePaginator<Issue> 有界卡片預覽
     */
    public function preview(Project $project, BoardSelectionData $selection): LengthAwarePaginator
    {
        $query = Issue::where('issues.workspace_id', $project->workspace_id)->where('issues.project_id', $project->id);
        $this->scope->preview($query, $selection);

        return $query->select(['issues.public_id', 'issues.identifier', 'issues.title', 'issues.type'])->orderBy('issues.identifier')->orderBy('issues.public_id')->paginate(20, ['*'], 'page', 1);
    }
}
