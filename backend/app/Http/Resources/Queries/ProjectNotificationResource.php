<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 僅序列化已預載通知及卡片識別；正文只在單筆投影存在。 */
class ProjectNotificationResource extends JsonResource
{
    /**
     * 不觸發資料查詢或回傳通知歷史相容欄位。
     *
     * @param  Request  $request  HTTP請求
     * @return array<string,mixed> 最小通知預覽
     */
    public function toArray(Request $request): array
    {
        $notice = $this->resource;
        $issue = $notice->getRelation('issue');
        $data = ['id' => $notice->public_id, 'subject' => $notice->subject, 'email' => $notice->email,
            'status' => $notice->status, 'kind' => $notice->kind ?? 'general', 'createdAt' => DatabaseTimes::public($notice->created_at),
            'issueId' => $issue->public_id, 'identifier' => $issue->identifier];
        if (array_key_exists('body', $notice->getAttributes())) {
            $data['body'] = $notice->body;
        }

        return $data;
    }
}
