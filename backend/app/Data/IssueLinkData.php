<?php

namespace App\Data;

/** 關聯驗證必要欄位；不攜帶描述、留言或歷史。 */
final readonly class IssueLinkData
{
    /** 建立指定卡片的關聯摘要與反向容量。
     * @param  string  $id  卡片公開ID
     * @param  string  $projectId  專案公開ID
     * @param  string  $type  卡片類型
     * @param  string  $parentIssueId  父項公開ID或空字串
     * @param  string  $epicId  Epic公開ID或空字串
     * @param  int  $relatedCount  目前反向關聯數
     * @param  bool  $alreadyRelated  是否已含目前卡片
     */
    public function __construct(
        public string $id,
        public string $projectId,
        public string $type,
        public string $parentIssueId,
        public string $epicId,
        public int $relatedCount,
        public bool $alreadyRelated,
    ) {}
}
