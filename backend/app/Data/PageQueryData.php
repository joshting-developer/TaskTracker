<?php

namespace App\Data;

/** 有上限且與HTTP無關的查詢頁面。 */
readonly class PageQueryData
{
    /**
     * 建立此用例的相依與唯讀資料，不從HTTP取得身分。
     *
     * @param  int  $page  頁碼（從1開始）
     * @param  int  $perPage  每頁筆數，已經Request驗證上限
     * @param  string  $search  搜尋字串
     */
    public function __construct(public int $page = 1, public int $perPage = 20, public string $search = '') {}
}
