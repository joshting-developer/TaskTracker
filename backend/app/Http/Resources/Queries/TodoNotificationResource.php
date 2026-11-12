<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 僅輸出收件人仍可讀的站內通知摘要。 */
class TodoNotificationResource extends JsonResource
{
    /**
     * 序列化通知識別、事件、標題及已讀時間，沒有待辦原稿／歷史。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 通知分頁項目
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource;

        return ['id' => $notification->id, 'todoId' => $notification->todoId, 'event' => $notification->event, 'actor' => $notification->actor, 'title' => $notification->title,
            'readAt' => DatabaseTimes::public($notification->read_at), 'createdAt' => DatabaseTimes::public($notification->created_at)];
    }
}
