<?php

namespace App\Factories;

use App\Authorization\ProjectAccess;
use App\Data\WorkspaceContext;

/** 為當次可信Context組裝限定租戶的ProjectAccess，不保存資格／讀取快照。 */
class ProjectAccessFactory
{
    /**
     * 注入明確Store組裝邊界，不提供依名稱任意解析服務的能力。
     *
     * @param  RecordStoreFactory  $stores  依工作區建立資料入口
     */
    public function __construct(private RecordStoreFactory $stores) {}

    /**
     * 建立後仍逐資源重驗有效permission，不將Context視為Project授權結果。
     *
     * @param  WorkspaceContext  $context  此用例重新驗證的身分及工作空間
     * @return ProjectAccess 本次用例專用的授權協作者
     */
    public function forContext(WorkspaceContext $context): ProjectAccess
    {
        return new ProjectAccess($this->stores->forContext($context), $context->legacyIdentity());
    }
}
