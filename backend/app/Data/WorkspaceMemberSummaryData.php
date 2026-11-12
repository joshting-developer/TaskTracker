<?php

namespace App\Data;

/** 工作區管理當頁成員及角色版本，不載入專案指派。 */
readonly class WorkspaceMemberSummaryData
{
    /**
     * 保存已計算可管理能力的管理摘要。
     *
     * @param  string  $id  使用者識別碼
     * @param  string  $name  顯示名稱
     * @param  string  $email  管理用途信箱
     * @param  string  $roleId  當前Workspace角色映射ID
     * @param  int  $revision  角色版本
     * @param  bool  $canChange  當次有效permission是否可管理此資格
     */
    public function __construct(public string $id, public string $name, public string $email, public string $roleId, public int $revision, public bool $canChange) {}
}
