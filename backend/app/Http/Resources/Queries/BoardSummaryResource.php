<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 序列化已預先聚合的看板摘要，不觸發任何關聯查詢。 */
class BoardSummaryResource extends JsonResource
{
    /**
     * 取得已授權看板與Project公開ID，避免讀取未預載關聯。
     *
     * @param  Board  $resource  已預載卡數的看板
     * @param  string  $projectId  Project公開ID
     */
    public function __construct(Board $resource, private string $projectId)
    {
        parent::__construct($resource);
    }

    /**
     * 明確列出列表需要的七個欄位。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 不含收錄ID與活動的摘要
     */
    public function toArray(Request $request): array
    {
        $board = $this->resource;

        return ['id' => $board->public_id, 'projectId' => $this->projectId, 'name' => $board->name,
            'revision' => (int) $board->revision, 'isDefault' => false, 'issueCount' => (int) $board->issue_count,
            'closedCount' => (int) $board->closed_count, 'archivedAt' => DatabaseTimes::public($board->archived_at)];
    }
}
