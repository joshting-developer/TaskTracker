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

        // TT-302：建卡者與最後一筆異動, 讓詳細頁不用翻異動紀錄就看得到；舊卡沒記建卡者時是空字串。
        $creator = $issue->getRelation('creator');

        return [...parent::toArray($request), 'description' => $issue->description,
            'createdBy' => $creator?->user_id ?? '', 'createdByName' => $creator?->getRelation('user')?->name ?? '',
            'lastChange' => $issue->last_at === null ? null : ['actor' => (string) $issue->last_actor, 'at' => (string) $issue->last_at],
            'activityCounts' => ['comments' => (int) $issue->comment_count, 'history' => (int) $issue->history_count,
                ...($issue->getAttribute('time_entry_count') !== null ? ['time' => (int) $issue->time_entry_count] : [])],
            'relatedIssueIds' => $refs->pluck('public_id')->all(),
            'references' => $linked->map(fn ($i) => ['id' => $i->public_id, 'projectId' => $issue->getRelation('project')->public_id, 'identifier' => $i->identifier, 'title' => $i->title, 'type' => $i->type, 'state' => $i->getRelation('status')->code])->values()->all()];
    }
}
