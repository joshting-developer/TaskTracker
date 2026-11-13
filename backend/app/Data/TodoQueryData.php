<?php

namespace App\Data;

/** 個人待辦列表查詢，不接受可信操作者身分。 */
readonly class TodoQueryData
{
    /**
     * 保存已驗證的範圍、生命週期與分頁。
     *
     * @param  PageQueryData  $pagination  有界分頁與搜尋
     * @param  string  $scope  mine／sent／involved
     * @param  string  $state  open／closed
     */
    public function __construct(public PageQueryData $pagination, public string $scope = 'mine', public string $state = 'open') {}
}
