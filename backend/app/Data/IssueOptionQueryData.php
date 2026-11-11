<?php

namespace App\Data;

/** 選項搜尋條件，不接受操作者或工作區。 */
readonly class IssueOptionQueryData
{
    /**
     * 保存已驗證的選項範圍。
     *
     * @param  PageQueryData  $pagination  有界搜尋與分頁
     * @param  string  $kind  issue／epic／parent
     * @param  string  $exclude  排除的公開Issue ID
     */
    public function __construct(public PageQueryData $pagination, public string $kind = 'issue', public string $exclude = '') {}
}
