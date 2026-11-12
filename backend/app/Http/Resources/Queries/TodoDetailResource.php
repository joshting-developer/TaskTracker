<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;

/** 單待辦原稿與摘要，不隱含全部歷史。 */
class TodoDetailResource extends TodoSummaryResource
{
    /**
     * 在已授權摘要上加入按需讀取的原稿及舊格式說明。
     *
     * @param  Request  $request  HTTP格式邊界
     * @return array<string,mixed> 單待辦內容
     */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'content' => $this->resource->content, 'note' => $this->resource->note];
    }
}
