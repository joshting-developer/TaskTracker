<?php

namespace App\Repositories;

use App\Data\ScheduleQueryData;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** 時程視圖的SQL投影, 只讀單一已授權Project內與區間重疊的卡片。 */
class ScheduleRepository
{
    /** 列表欄位, 不讀描述、歷史或工時 */
    private const COLUMNS = ['id', 'workspace_id', 'public_id', 'project_id', 'identifier', 'title', 'type', 'state_id', 'priority', 'assignee_id', 'epic_id', 'start_date', 'end_date', 'due_date', 'revision'];

    /**
     * 單端日期視為單日；兩端皆空不列入。排序先開始日再結束日, 以卡號與公開ID穩定換頁。
     *
     * @param  Project  $project  已授權Project
     * @param  ScheduleQueryData  $filters  有界區間與分頁
     * @return LengthAwarePaginator<Issue> 與區間重疊的卡片
     */
    public function page(Project $project, ScheduleQueryData $filters): LengthAwarePaginator
    {
        return $this->scope($project, $filters)
            // 單端卡片的另一端以已填日期代替, 與前端條狀計算一致。
            ->where(fn ($q) => $q->where('start_date', '<=', $filters->to)->orWhere(fn ($q) => $q->whereNull('start_date')->where('end_date', '<=', $filters->to)))
            ->where(fn ($q) => $q->where('end_date', '>=', $filters->from)->orWhere(fn ($q) => $q->whereNull('end_date')->where('start_date', '>=', $filters->from)))
            ->with(['status:id,code,label', 'assignee:id,user_id,disabled_at', 'assignee.user:id,name',
                // TT-386：Epic只取同租戶同Project, 甘特依此分組；不回其他Project的標題。
                'epic' => fn ($q) => $q->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->select(['id', 'public_id', 'identifier', 'title'])])
            ->orderByRaw('COALESCE(start_date, end_date)')->orderByRaw('COALESCE(end_date, start_date)')->orderBy('identifier')->orderBy('public_id')
            ->paginate($filters->page->perPage, self::COLUMNS, 'page', $filters->page->page);
    }

    /**
     * 同篩選下尚未排程的卡片數, 只回計數不回列。
     *
     * @param  Project  $project  已授權Project
     * @param  ScheduleQueryData  $filters  承辦者條件
     * @return int 兩端日期皆空的卡片數
     */
    public function unscheduled(Project $project, ScheduleQueryData $filters): int
    {
        return $this->scope($project, $filters)->whereNull('start_date')->whereNull('end_date')->count();
    }

    /**
     * 租戶、Project與承辦者條件。
     *
     * @param  Project  $project  已授權Project
     * @param  ScheduleQueryData  $filters  承辦者條件
     * @return Builder<Issue> 基礎查詢
     */
    private function scope(Project $project, ScheduleQueryData $filters): Builder
    {
        return Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->when($filters->assignee !== '', fn ($q) => $q->whereHas('assignee', fn ($q) => $q->where('user_id', $filters->assignee)));
    }
}
