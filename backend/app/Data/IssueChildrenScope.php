<?php

namespace App\Data;

/** 同租戶父任務與目標Epic的資料庫鍵, 不作HTTP輸出 */
final readonly class IssueChildrenScope
{
    /**
     * 保存Repository解析的同專案外鍵
     *
     * @param  int  $workspaceId  工作區內部鍵
     * @param  int  $projectId  專案內部鍵
     * @param  int  $parentId  父任務內部鍵
     * @param  ?int  $epicId  目標Epic內部鍵, null代表清空
     */
    public function __construct(public int $workspaceId, public int $projectId, public int $parentId, public ?int $epicId) {}
}
