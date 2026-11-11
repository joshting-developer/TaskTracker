<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 僅序列化明確預載的列表投影，不查DB或輸出留言／歷史／描述。 */
class IssueSummaryResource extends JsonResource
{
    /** 序列化已授權且預載的投影，不做任何查詢。 */
    public function toArray(Request $request): array
    {
        $i = $this->resource;

        return ['id' => $i->public_id, 'projectId' => $i->getRelation('project')->public_id,
            'identifier' => $i->identifier, 'title' => $i->title, 'type' => $i->type,
            'state' => $i->getRelation('status')->code, 'priority' => $i->priority,
            'assignee' => $i->getRelation('assignee')?->user_id ?? '', 'dueDate' => $i->due_date ?? '',
            'epicId' => $i->getRelation('epic')?->public_id ?? '', 'parentIssueId' => $i->getRelation('parent')?->public_id ?? '',
            'estimateMinutes' => (int) $i->estimate_minutes, 'revision' => (int) $i->revision,
            'createdAt' => DatabaseTimes::public($i->created_at), 'updatedAt' => DatabaseTimes::public($i->updated_at)];
    }
}
