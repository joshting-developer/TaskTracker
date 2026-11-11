<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;

/** 單卡詳細屬性；留言／異動需另外分頁取得，不自動附加全集合。 */
class IssueDetailResource extends IssueSummaryResource
{
    /** 序列化已授權且預載的投影，不做任何查詢。 */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'description' => $this->resource->description];
    }
}
