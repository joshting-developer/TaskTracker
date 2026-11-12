<?php

namespace App\Http\Resources\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 分頁輸出共用形狀，不包含框架內部URL或Model。 */
class PageResource
{
    /**
     * 統一分頁欄位，不暴露框架內部URL。
     *
     * @param  LengthAwarePaginator  $page  分頁結果
     * @param  array  $items  已轉換的當頁資料
     * @return array{items:list<mixed>,page:int,perPage:int,total:int,lastPage:int} 分頁回應
     */
    public static function wrap(LengthAwarePaginator $page, array $items): array
    {
        return ['items' => $items, 'page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => max(1, $page->lastPage())];
    }
}
