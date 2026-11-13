<?php

namespace App\Http\Resources\Queries;

use App\Data\IssueQueryData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 保留真實總數與頁數, 另提供任務列表可翻閱的OFFSET界限 */
class IssueListPageResource
{
    /**
     * 同一形狀用於Session與公開PAT列表, 不截斷總筆數
     *
     * @param  LengthAwarePaginator  $page  已限定Project的查詢結果
     * @param  array  $items  當頁已轉換摘要
     * @return array 真實分頁與可到達頁數／最大OFFSET
     */
    public static function wrap(LengthAwarePaginator $page, array $items): array
    {
        return [...PageResource::wrap($page, $items), 'maxOffset' => IssueQueryData::MAX_LIST_OFFSET,
            'accessibleLastPage' => min(max(1, $page->lastPage()), intdiv(IssueQueryData::MAX_LIST_OFFSET, $page->perPage()) + 1)];
    }
}
