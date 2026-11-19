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

        return ['tags' => $i->getRelation('tags')->map(fn ($tag) => ['id' => $tag->public_id, 'name' => $tag->name, 'color' => $tag->color])->values()->all(), 'id' => $i->public_id, 'projectId' => $i->getRelation('project')->public_id,
            'identifier' => $i->identifier, 'title' => $i->title, 'type' => $i->type,
            'state' => $i->getRelation('status')->code, 'priority' => $i->priority,
            'assignee' => $i->getRelation('assignee')?->user_id ?? '', 'assigneeName' => $i->getRelation('assignee')?->getRelation('user')?->name ?? '', 'assigneeDisabled' => $i->getRelation('assignee')?->disabled_at !== null, 'dueDate' => $i->due_date ?? '',
            'epicId' => $i->getRelation('epic')?->public_id ?? '',
            'epic' => $i->getRelation('epic') ? ['id' => $i->getRelation('epic')->public_id, 'projectId' => $i->getRelation('project')->public_id, 'title' => $i->getRelation('epic')->title, 'identifier' => $i->getRelation('epic')->identifier, 'type' => 'epic'] : null, 'parentIssueId' => $i->getRelation('parent')?->public_id ?? '',
            'estimateMinutes' => (int) ($i->type === 'epic' ? ($i->epic_estimate_minutes ?? $i->estimate_minutes) : $i->estimate_minutes),
            ...($i->getAttribute('spent_minutes') !== null ? ['spentMinutes' => (int) $i->spent_minutes] : []), 'revision' => (int) $i->revision,
            // TT-428：只有看板卡片查詢會帶位置欄位, null 代表這張看板還沒排過這張卡
            ...(array_key_exists('board_position', $i->getAttributes()) ? ['boardPosition' => $i->getAttribute('board_position')] : []),
            'createdAt' => DatabaseTimes::public($i->created_at), 'updatedAt' => DatabaseTimes::public($i->updated_at)];
    }
}
