<?php

namespace App\Repositories;

use App\Data\ScheduleQueryData;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        $query = $this->scope($project, $filters)
            ->where('schedule_start', '<=', $filters->to)
            ->where('schedule_end', '>=', $filters->from);
        // title 必填且非索引欄位, 讓 COUNT 也用 ICP 過濾後才讀列。
        $total = (clone $query)->count('title');

        return $query->with(['status:id,code,label', 'assignee:id,user_id,disabled_at', 'assignee.user:id,name',
            // TT-386：Epic只取同租戶同Project, 甘特依此分組；不回其他Project的標題。
            'epic' => fn ($q) => $q->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->select(['id', 'public_id', 'identifier', 'title'])])
            ->orderBy('schedule_start')->orderBy('schedule_end')->orderBy('identifier')->orderBy('public_id')
            ->paginate($filters->page->perPage, self::COLUMNS, 'page', $filters->page->page, $total);
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
        return $this->scope($project, $filters)->whereNull('schedule_end')->count();
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
        // 避免 ORDER BY/LIMIT 誘使最佳化器改掃整個工作區。
        return Issue::forceIndex($filters->includeClosed ? 'issues_schedule_range' : 'issues_schedule_state_range')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->when(! $filters->includeClosed, fn ($q) => $q->whereIn('state_id', ProjectStatus::where('project_id', $project->id)->where('code', '!=', 'closed')->pluck('id')))
            ->when($filters->assignee !== '', fn ($q) => $q->whereHas('assignee', fn ($q) => $q->where('user_id', $filters->assignee)));
    }
}
