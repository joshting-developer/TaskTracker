<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 單卡有效工時的公開投影，不執行查詢或夾帶歷史。 */
class TimeEntryResource extends JsonResource
{
    /**
     * 序列化已依作者資格限定的有效工時。
     *
     * @param  Request  $request  HTTP輸出邊界
     * @return array<string,mixed> 編輯工時需要的公開欄位
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return ['id' => $entry->id, 'issueId' => $entry->issueId, 'projectId' => $entry->projectId,
            'issue' => ['id' => $entry->issueId, 'projectId' => $entry->projectId, 'identifier' => $entry->issueIdentifier, 'title' => $entry->issueTitle, 'type' => $entry->issueType],
            'userId' => $entry->userId, 'userName' => $entry->userName, 'workDate' => $entry->workDate,
            'minutes' => (int) $entry->minutes, 'note' => $entry->note, 'revision' => (int) $entry->revision,
            'voidedAt' => null, 'createdAt' => DatabaseTimes::public($entry->created_at), 'updatedAt' => DatabaseTimes::public($entry->updated_at)];
    }
}
