<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 僅序列化明確預載的列表投影，不查DB或輸出留言／歷史／描述。 */
class IssueSummaryResource extends JsonResource
{
    /**
     * 序列化已授權且預載的投影，不發出額外查詢。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 明確公開欄位
     */
    public function toArray(Request $request): array
    {
        $i = $this->resource;

        return ['id' => $i->public_id, 'projectId' => $i->getRelation('project')->public_id,
            'identifier' => $i->identifier, 'title' => $i->title, 'type' => $i->type,
            'state' => $i->getRelation('status')->code, 'priority' => $i->priority,
            'assignee' => $i->getRelation('assignee')?->user_id ?? '', 'assigneeName' => $i->getRelation('assignee')?->getRelation('user')?->name ?? '', 'dueDate' => $i->due_date ?? '',
            'epicId' => $i->getRelation('epic')?->public_id ?? '',
            'epic' => $i->getRelation('epic') ? ['id' => $i->getRelation('epic')->public_id, 'projectId' => $i->getRelation('project')->public_id, 'title' => $i->getRelation('epic')->title, 'identifier' => $i->getRelation('epic')->identifier, 'type' => 'epic'] : null, 'parentIssueId' => $i->getRelation('parent')?->public_id ?? '',
            'estimateMinutes' => (int) ($i->type === 'epic' ? ($i->epic_estimate_minutes ?? $i->estimate_minutes) : $i->estimate_minutes),
            ...($i->getAttribute('spent_minutes') !== null ? ['spentMinutes' => (int) $i->spent_minutes] : []), 'revision' => (int) $i->revision,
            'createdAt' => DatabaseTimes::public($i->created_at), 'updatedAt' => DatabaseTimes::public($i->updated_at)];
    }
}
