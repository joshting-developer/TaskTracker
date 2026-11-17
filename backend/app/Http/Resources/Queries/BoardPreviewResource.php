<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 預覽只包含可辨識卡片的四個欄位, 不觸發關聯查詢 */
class BoardPreviewResource extends JsonResource
{
    /** 輸出已授權且已投影的卡片標識
     * @param  Request  $request  HTTP 輸出邊界
     * @return array{id:string,identifier:string,title:string,type:string} 預覽摘要
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->public_id, 'identifier' => $this->resource->identifier, 'title' => $this->resource->title, 'type' => $this->resource->type];
    }
}
