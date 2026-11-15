<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 專案定義的公開投影, 不包含內部外鍵或名稱索引 */
class TagResource extends JsonResource
{
    /** 序列化已載入定義, 不發出額外資料庫查詢
     *
     * @param  Request  $request  HTTP 格式邊界
     * @return array<string,mixed> 標籤定義
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->public_id, 'name' => $this->resource->name, 'color' => $this->resource->color, 'description' => $this->resource->description,
            'revision' => (int) $this->resource->revision, 'createdAt' => DatabaseTimes::public($this->resource->created_at), 'updatedAt' => DatabaseTimes::public($this->resource->updated_at)];
    }
}
