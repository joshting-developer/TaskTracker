<?php

namespace App\Repositories;

use App\Data\TimeReportData;
use App\Data\TimeReportEntryData;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

/** 工時資料存取：只查租戶及已授權專案的有效期間，不判斷角色或權限。 */
class TimeReportRepository
{
    /**
     * @param  string  $workspaceId  伺服器選定的工作區公開ID
     * @param  array  $projectIds  Service已授權的專案公開ID
     * @param  TimeReportData  $filters  不含身分的查詢條件
     * @return Collection<int, TimeReportEntryData> 已排序且明確投影的原始工時
     */
    public function entries(string $workspaceId, array $projectIds, TimeReportData $filters): Collection
    {
        $columns = ['time_entries.public_id as entry_id', 'projects.public_id as project_public_id', 'workspace_members.user_id', 'issues.public_id as issue_public_id', 'time_entries.work_date', 'time_entries.minutes'];
        if ($filters->detailGroup !== null) {
            $columns[] = 'issues.identifier';
            $columns[] = 'issues.title';
        }
        if ($filters->entryGroupKey !== '') {
            $columns[] = 'time_entries.note';
        }

        return TimeEntry::query()
            ->join('workspaces', 'workspaces.id', '=', 'time_entries.workspace_id')
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->join('workspace_members', 'workspace_members.id', '=', 'time_entries.member_id')
            ->join('issues', 'issues.id', '=', 'time_entries.issue_id')
            ->where('workspaces.public_id', $workspaceId)
            ->whereColumn('projects.workspace_id', 'time_entries.workspace_id')
            ->whereColumn('workspace_members.workspace_id', 'time_entries.workspace_id')
            ->whereColumn('issues.workspace_id', 'time_entries.workspace_id')
            ->whereColumn('issues.project_id', 'time_entries.project_id')
            ->whereIn('projects.public_id', $projectIds)
            ->whereNull('time_entries.voided_at')
            ->whereBetween('time_entries.work_date', [$filters->startDate, $filters->endDate])
            ->when($filters->projectId !== '', fn ($query) => $query->where('projects.public_id', $filters->projectId))
            ->when($filters->userId !== '', fn ($query) => $query->where('workspace_members.user_id', $filters->userId))
            ->orderBy('time_entries.id')->get($columns)
            ->map(fn ($row) => new TimeReportEntryData(
                $row->entry_id, $row->project_public_id, $row->user_id, $row->issue_public_id,
                $row->work_date, (int) $row->minutes, $row->note ?? '',
                $filters->detailGroup === null ? null : $row->identifier,
                $filters->detailGroup === null ? null : $row->title,
            ));
    }
}
