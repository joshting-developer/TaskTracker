<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;

/** 單卡詳細屬性；留言／異動需另外分頁取得，不自動附加全集合。 */
class IssueDetailResource extends IssueSummaryResource
{
    /**
     * 序列化已授權且預載的投影，不發出額外查詢。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 明確公開欄位
     */
    public function toArray(Request $request): array
    {
        $issue = $this->resource;
        $refs = $issue->getRelation('relatedIssues');
        $linked = collect([$issue->getRelation('epic'), $issue->getRelation('parent')])->filter()->concat($refs)->unique('public_id');

        return [...parent::toArray($request), 'description' => $issue->description,
            'relatedIssueIds' => $refs->pluck('public_id')->all(),
            'references' => $linked->map(fn ($i) => ['id' => $i->public_id, 'projectId' => $issue->getRelation('project')->public_id, 'identifier' => $i->identifier, 'title' => $i->title, 'type' => $i->type])->values()->all()];
    }
}
