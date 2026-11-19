<?php

namespace App\Repositories;

use App\Models\Board;
use App\Models\Issue;
use App\Models\Project;
use App\Queries\BoardIssueScope;
use Illuminate\Support\Facades\DB;

/** 看板手動排序的存取, 只處理已限定工作區與 Project 的資料, 不判斷權限 */
class BoardPositionRepository
{
    /** 注入看板收錄條件, 寫入前確認卡片仍在看板內
     * @param  BoardIssueScope  $scope  自訂看板收錄的 SQL 條件
     */
    public function __construct(private BoardIssueScope $scope) {}

    /** 依公開 ID 找出仍在看板內的卡片內部 ID
     * @param  Project  $project  已授權 Project
     * @param  Board|null  $board  null 代表 Default
     * @param  list<string>  $publicIds  至多 500 個不重複公開 ID
     * @return array<string,int> 公開 ID 對內部 ID
     */
    public function boardIssueIds(Project $project, ?Board $board, array $publicIds): array
    {
        $q = Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->whereIn('public_id', $publicIds);
        if ($board !== null) {
            $this->scope->apply($q, $board->id);
        }

        return $q->pluck('id', 'public_id')->map(fn ($id) => (int) $id)->all();
    }

    /** 依新順序一次改寫這些卡片的位置, 沒列到的卡片不動
     * @param  Project  $project  已授權 Project
     * @param  Board|null  $board  null 代表 Default
     * @param  list<int>  $issueIds  新順序的內部 ID, 上方在前
     * @return void
     */
    public function save(Project $project, ?Board $board, array $issueIds): void
    {
        $rows = array_map(fn (int $id, int $index) => ['workspace_id' => $project->workspace_id, 'project_id' => $project->id, 'board_id' => $board?->id, 'board_key' => $board?->id ?? 0,
            'issue_id' => $id, 'position' => ($index + 1) * 1024, 'revision' => 1], $issueIds, array_keys($issueIds));
        // 既有列只更新位置並把 revision 加一
        DB::table('board_card_positions')->upsert($rows, ['project_id', 'board_key', 'issue_id'], ['position' => DB::raw('VALUES(`position`)'), 'revision' => DB::raw('`revision` + 1')]);
    }

    /** 批次讀取這一頁卡片在看板上的位置
     * @param  Project  $project  已授權 Project
     * @param  Board|null  $board  null 代表 Default
     * @param  list<int>  $issueIds  當頁至多 100 個內部 ID
     * @return array<int,int> 內部 ID 對位置, 沒排過的卡片不在結果內
     */
    public function positions(Project $project, ?Board $board, array $issueIds): array
    {
        if ($issueIds === []) {
            return [];
        }

        return DB::table('board_card_positions')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->where('board_key', $board?->id ?? 0)->whereIn('issue_id', $issueIds)->pluck('position', 'issue_id')->map(fn ($p) => (int) $p)->all();
    }

    /** 看板版本用的排序變更來源, 只取計數與 revision 加總
     * @param  Project  $project  已授權 Project
     * @param  Board|null  $board  null 代表 Default
     * @return array{total:int,revisions:int} 排序列數與 revision 加總
     */
    public function versionParts(Project $project, ?Board $board): array
    {
        $row = DB::table('board_card_positions')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->where('board_key', $board?->id ?? 0)->selectRaw('COUNT(*) AS total, COALESCE(SUM(revision),0) AS revisions')->first();

        return ['total' => (int) $row->total, 'revisions' => (int) $row->revisions];
    }
}
