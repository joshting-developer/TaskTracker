<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 個人待辦摘要投影，原稿與歷史由其他介面提供。 */
class TodoSummaryResource extends JsonResource
{
    /**
     * 接收已授權資料與用例計算的操作能力，不自行授權或查詢。
     *
     * @param  Todo  $resource  已限定參與者的待辦
     * @param  array<string,bool>  $capabilities  當次操作能力
     * @param  array<string,string>|null  $link  已通過Project資格的轉卡連結
     */
    public function __construct(Todo $resource, private array $capabilities, private ?array $link = null)
    {
        parent::__construct($resource);
    }

    /**
     * 序列化最小列表欄位，沒有原稿、note、legacy或歷史。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 待辦摘要
     */
    public function toArray(Request $request): array
    {
        $todo = $this->resource;

        return ['id' => $todo->public_id, 'title' => $todo->title, 'hasDetails' => (bool) $todo->has_details, 'createdBy' => $todo->getRelation('creator')->user_id, 'creatorName' => $todo->creator_name,
            'assigneeId' => $todo->getRelation('assignee')->user_id, 'initialAssigneeId' => $todo->getRelation('initialAssignee')->user_id, 'initialAssigneeName' => $todo->getRelation('initialAssignee')->getRelation('user')->name, 'assigneeName' => $todo->assignee_name,
            'state' => $todo->state, 'revision' => (int) $todo->revision, 'createdAt' => DatabaseTimes::public($todo->created_at), 'updatedAt' => DatabaseTimes::public($todo->updated_at),
            'completedAt' => DatabaseTimes::public($todo->completed_at), 'returnedAt' => DatabaseTimes::public($todo->returned_at), 'convertedIssue' => $this->link, 'capabilities' => $this->capabilities];
    }
}
