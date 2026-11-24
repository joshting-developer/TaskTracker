<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 首頁只輸出開卡與日期摘要所需欄位。 */
class PersonalDashboardResource extends JsonResource
{
    /**
     * 僅使用已預載的專案與狀態, 不查詢描述或歷史。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 本人工作摘要
     */
    public function toArray(Request $request): array
    {
        $issue = $this->resource;

        return ['id' => $issue->public_id, 'projectId' => $issue->getRelation('project')->public_id,
            'projectName' => $issue->getRelation('project')->name, 'identifier' => $issue->identifier, 'title' => $issue->title,
            'state' => $issue->getRelation('status')->code, 'stateLabel' => $issue->getRelation('status')->label,
            'dueDate' => $issue->due_date ?? '', 'startDate' => $issue->start_date ?? '', 'updatedAt' => DatabaseTimes::public($issue->updated_at)];
    }
}
