<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 時程條狀只輸出開卡、日期與承辦所需欄位。 */
class ScheduleIssueResource extends JsonResource
{
    /**
     * 僅使用已預載的狀態與承辦者, 日期未填為空字串。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 時程卡片
     */
    public function toArray(Request $request): array
    {
        $issue = $this->resource;
        $assignee = $issue->getRelation('assignee');

        return ['id' => $issue->public_id, 'identifier' => $issue->identifier, 'title' => $issue->title, 'type' => $issue->type, 'priority' => $issue->priority,
            'state' => $issue->getRelation('status')->code, 'stateLabel' => $issue->getRelation('status')->label,
            'assignee' => $assignee?->user_id ?? '', 'assigneeName' => $assignee?->getRelation('user')?->name ?? '', 'assigneeDisabled' => (bool) $assignee?->disabled_at,
            'startDate' => $issue->start_date ?? '', 'endDate' => $issue->end_date ?? '', 'dueDate' => $issue->due_date ?? '', 'revision' => $issue->revision];
    }
}
