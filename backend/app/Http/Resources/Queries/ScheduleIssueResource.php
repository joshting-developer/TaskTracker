<?php

namespace App\Http\Resources\Queries;

use App\Models\Issue;
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
            'startDate' => $issue->start_date ?? '', 'endDate' => $issue->end_date ?? '', 'dueDate' => $issue->due_date ?? '', 'epicId' => $issue->getRelation('epic')?->public_id ?? '', 'revision' => $issue->revision];
    }

    /**
     * 當頁卡片引用的Epic, 依卡號排序去重；甘特群組標題用, Epic本身沒有日期時也有名稱。
     *
     * @param  array<int,Issue>  $issues  已預載同Project Epic的當頁卡片
     * @return array<int,array{id:string,identifier:string,title:string}> Epic摘要
     */
    public static function epics(array $issues): array
    {
        $epics = [];
        foreach ($issues as $issue) {
            $epic = $issue->getRelation('epic');
            if ($epic) {
                $epics[$epic->public_id] = ['id' => $epic->public_id, 'identifier' => $epic->identifier, 'title' => $epic->title];
            }
        }
        usort($epics, fn ($a, $b) => strnatcmp($a['identifier'], $b['identifier']));

        return $epics;
    }
}
