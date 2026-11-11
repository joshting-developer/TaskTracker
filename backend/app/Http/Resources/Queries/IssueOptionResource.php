<?php

namespace App\Http\Resources\Queries;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 僅供已授權選擇器的識別與標題投影，不當成編輯快照。 */
class IssueOptionResource extends JsonResource
{
    /**
     * 傳入可信Project識別，避免序列化時查資料庫。
     *
     * @param  Issue  $resource  預載最小參照的候選
     * @param  string  $projectId  已授權Project公開ID
     */
    public function __construct(Issue $resource, private string $projectId)
    {
        parent::__construct($resource);
    }

    /**
     * 建立選項與父子歸屬提示，不輸出活動／描述／工時。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 最小選項
     */
    public function toArray(Request $request): array
    {
        $issue = $this->resource;

        return ['id' => $issue->public_id, 'projectId' => $this->projectId, 'identifier' => $issue->identifier,
            'title' => $issue->title, 'type' => $issue->type, 'epicId' => $issue->getRelation('epic')?->public_id ?? '',
            'parentIssueId' => $issue->getRelation('parent')?->public_id ?? '', 'state' => $issue->getRelation('status')->code];
    }
}
