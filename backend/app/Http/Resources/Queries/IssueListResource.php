<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 純任務列表欄位，不要求看板聚合、詳細關聯或活動。 */
class IssueListResource extends JsonResource
{
    /**
     * 序列化已限定Project及預載的列表投影，不查DB。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 列表顯示與開啟資源需要的欄位
     */
    public function toArray(Request $request): array
    {
        $issue = $this->resource;

        return ['id' => $issue->public_id, 'projectId' => $issue->getRelation('project')->public_id,
            'identifier' => $issue->identifier, 'title' => $issue->title, 'type' => $issue->type,
            'state' => $issue->getRelation('status')->code, 'priority' => $issue->priority,
            'assignee' => $issue->getRelation('assignee')?->user_id ?? '',
            'assigneeName' => $issue->getRelation('assignee')?->getRelation('user')?->name ?? '',
            'dueDate' => $issue->due_date ?? '', 'revision' => (int) $issue->revision,
            'createdAt' => DatabaseTimes::public($issue->created_at), 'updatedAt' => DatabaseTimes::public($issue->updated_at)];
    }
}
