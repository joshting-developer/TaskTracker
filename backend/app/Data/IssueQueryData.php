<?php

namespace App\Data;

/** 任務列表查詢；不包含操作者或可信工作區ID。 */
readonly class IssueQueryData
{
    /**
     * 建立此用例的相依與唯讀資料，不從HTTP取得身分。
     *
     * @param  PageQueryData  $pagination  分頁與搜尋
     * @param  array  $states  狀態type篩選
     * @param  string  $priority  優先級篩選
     * @param  string  $type  卡片類型篩選
     * @param  string  $assignee  承辦者User ID
     * @param  string  $epicId  Epic公開ID，unlinked代表未掛Epic
     * @param  string  $condition  overdue或unassigned等條件
     * @param  string  $sort  排序方式（updated／created／priority／due）
     * @param  string  $scope  all或mine
     * @param  string  $identifier  精確卡號
     * @param  string  $direction  排序方向asc／desc；空字串用各排序的預設（TT-306）
     */
    public function __construct(
        public PageQueryData $pagination,
        public array $states = [],
        public string $priority = '',
        public string $type = '',
        public string $assignee = '',
        public string $epicId = '',
        public string $condition = '',
        public string $sort = 'updated',
        public string $scope = 'all',
        public string $identifier = '',
        public string $direction = '',
    ) {}
}
