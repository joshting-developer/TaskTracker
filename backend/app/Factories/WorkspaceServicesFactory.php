<?php

namespace App\Factories;

use App\Repositories\IssueChildrenRepository;
use App\Repositories\ProjectReadRepository;
use App\Repositories\TimeReportRepository;

/**
 * 依交易內重驗的Session建立單次操作的服務組；本身不保存租戶狀態，可由Container自動解析。
 */
class WorkspaceServicesFactory
{
    /**
     * 注入建立限定Store與報表查詢所需的無狀態協作者。
     *
     * @param  RecordStoreFactory  $stores  依Workspace建立資料入口
     * @param  TimeReportRepository  $timeReports  工時分析查詢
     * @param  ProjectReadRepository  $projects  訊號目標專案資料入口
     * @param  IssueChildrenRepository  $children  子任務最小投影與批次寫入
     */
    public function __construct(private RecordStoreFactory $stores, private TimeReportRepository $timeReports, private ProjectReadRepository $projects, private IssueChildrenRepository $children) {}

    /**
     * 建立本次操作專用的服務組；每次呼叫都是新的實例，不跨操作共用。
     *
     * @param  array<string,mixed>  $workspaceSession  交易內由Session重驗的身分、工作區與權限
     * @return WorkspaceOperationServices 按需初始化的服務組
     */
    public function forSession(array $workspaceSession): WorkspaceOperationServices
    {
        return new WorkspaceOperationServices($this->stores->forWorkspace($workspaceSession['workspace']['id']), $workspaceSession, $this->timeReports, $this->projects, $this->children);
    }
}
