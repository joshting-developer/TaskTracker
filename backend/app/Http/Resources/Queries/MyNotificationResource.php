<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** TT-315：公開API「我的通知」的一筆摘要；不帶正文和收件Email, 要看內容請讀那張卡或它的留言。 */
class MyNotificationResource extends JsonResource
{
    /**
     * 只用已預載的卡片與專案識別, 不另外查資料。
     *
     * @param  Request  $request  HTTP請求
     * @return array<string,mixed> 通知摘要
     */
    public function toArray(Request $request): array
    {
        $notice = $this->resource;
        $issue = $notice->getRelation('issue');

        return ['id' => $notice->public_id, 'kind' => $notice->kind ?? 'general', 'subject' => $notice->subject,
            'projectId' => $notice->getRelation('project')->public_id, 'issueId' => $issue->public_id, 'identifier' => $issue->identifier,
            'createdAt' => DatabaseTimes::public($notice->created_at)];
    }
}
