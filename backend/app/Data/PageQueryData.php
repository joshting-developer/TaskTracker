<?php

namespace App\Data;

/** 有上限且與HTTP無關的查詢頁面。 */
readonly class PageQueryData
{
    /** 建立此用例的相依與唯讀資料，不從HTTP取得身分。 */
    public function __construct(public int $page = 1, public int $perPage = 20, public string $search = '') {}
}
