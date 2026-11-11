<?php

namespace App\Data;

/** 任務列表查詢；不包含操作者或可信工作區ID。 */
readonly class IssueQueryData
{
    /** 建立此用例的相依與唯讀資料，不從HTTP取得身分。 */
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
    ) {}
}
