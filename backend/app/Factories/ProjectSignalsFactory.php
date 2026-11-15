<?php

namespace App\Factories;

use App\Data\WorkspaceContext;
use App\Repositories\ProjectReadRepository;
use App\Services\ProjectSignals;

/** 只為當次可信租戶組裝既有廣播訊號, 不保存身分 */
class ProjectSignalsFactory
{
    /** 注入不持有租戶狀態的訊號資料入口
     *
     * @param ProjectReadRepository $projects 同租戶專案訊號資料入口 */
    public function __construct(private ProjectReadRepository $projects) {}

    /** 為當次可信租戶組裝廣播協作者
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分
     * @return ProjectSignals 單次租戶訊號協作者
     */
    public function forContext(WorkspaceContext $context): ProjectSignals
    {
        return new ProjectSignals($context->legacyIdentity(), $this->projects);
    }
}
