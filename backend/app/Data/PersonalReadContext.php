<?php

namespace App\Data;

/** 每次交易內驗證的個人讀取範圍，不保存於singleton或跨請求快取。 */
readonly class PersonalReadContext
{
    /**
     * 保存同工作區的資料庫鍵及操作者公開ID。
     *
     * @param  int  $workspaceId  已驗證Workspace內部鍵
     * @param  int  $memberId  已驗證Member內部鍵
     * @param  string  $userId  操作者User ID
     * @param  int  $roleRevision  當次Workspace資格版本
     */
    public function __construct(public int $workspaceId, public int $memberId, public string $userId, public int $roleRevision = 0) {}
}
