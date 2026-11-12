<?php

namespace App\Data;

/** 單次Project管理查詢的成員及有效操作能力，不保存跨請求資格。 */
readonly class ProjectMemberSummaryData
{
    /**
     * 保存已批次授權的最小管理資料。
     *
     * @param  string  $userId  使用者識別碼
     * @param  string  $name  顯示名稱
     * @param  string  $email  管理用途信箱
     * @param  string  $source  workspace或project授權來源
     * @param  string|null  $roleId  有效專案指派角色
     * @param  bool  $canChange  是否可調整這筆資格
     */
    public function __construct(public string $userId, public string $name, public string $email, public string $source, public ?string $roleId, public bool $canChange) {}
}
