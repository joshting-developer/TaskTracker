<?php

namespace App\Factories;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspaceOperationAccess;
use App\Repositories\ProjectReadRepository;
use App\Repositories\RecordStore;
use App\Repositories\TimeReportRepository;
use App\Services\IssueService;
use App\Services\ProjectBoards;
use App\Services\ProjectMembers;
use App\Services\ProjectService;
use App\Services\ProjectSignals;
use App\Services\TimeEntryService;
use App\Services\WorkspaceQuery;
use App\Services\WorkspaceTimeReport;

/**
 * 單一Workspace操作的組裝邊界：同一操作共用一個限定Store與ProjectAccess，領域服務第一次使用時才建立。
 * 只在WorkspaceServicesFactory於交易內以可信Session建立；不可跨操作、請求或Queue任務保存。
 */
final class WorkspaceOperationServices
{
    /** @var array<string,object> 已建立的協作者，依名稱記錄供按需初始化驗證 */
    private array $created = [];

    /**
     * 綁定本次操作的可信範圍與穩定協作者。
     *
     * @param  RecordStore  $store  已限定目前Workspace的資料入口
     * @param  array<string,mixed>  $workspaceSession  交易內由Session重驗的身分、工作區與權限
     * @param  TimeReportRepository  $timeReports  工時分析查詢（無租戶狀態）
     * @param  ProjectReadRepository  $projects  訊號目標專案資料入口
     */
    public function __construct(public readonly RecordStore $store, public readonly array $workspaceSession, private TimeReportRepository $timeReports, private ProjectReadRepository $projects) {}

    /**
     * 取得或建立指定協作者並記錄名稱。
     *
     * @template T of object
     *
     * @param  string  $name  協作者名稱
     * @param  \Closure(): T  $make  建立方式
     * @return T 本次操作內共用的實例
     */
    private function once(string $name, \Closure $make): object
    {
        return $this->created[$name] ??= $make();
    }

    /**
     * 本次操作已初始化的協作者名稱，依建立順序。
     *
     * @return list<string> 協作者名稱
     */
    public function created(): array
    {
        return array_keys($this->created);
    }

    /**
     * Project層級的有效permission與scope檢查。
     *
     * @return ProjectAccess 本次操作共用的授權協作者
     */
    public function access(): ProjectAccess
    {
        return $this->once('access', fn () => new ProjectAccess($this->store, $this->workspaceSession));
    }

    /**
     * RPC操作的入口授權。
     *
     * @return WorkspaceOperationAccess 操作授權
     */
    public function operationAccess(): WorkspaceOperationAccess
    {
        return $this->once('operationAccess', fn () => new WorkspaceOperationAccess($this->store, $this->workspaceSession, $this->access()));
    }

    /**
     * Project建立、設定與狀態。
     *
     * @return ProjectService Project用例
     */
    public function projects(): ProjectService
    {
        return $this->once('projects', fn () => new ProjectService($this->store, $this->workspaceSession, $this->access()));
    }

    /**
     * 卡片寫入；看板收錄只在建立時帶boardId才初始化。
     *
     * @return IssueService 卡片用例
     */
    public function issues(): IssueService
    {
        return $this->once('issues', fn () => new IssueService($this->store, $this->workspaceSession, $this->access(), fn () => $this->boards()));
    }

    /**
     * 工時填報。
     *
     * @return TimeEntryService 工時用例
     */
    public function time(): TimeEntryService
    {
        return $this->once('time', fn () => new TimeEntryService($this->store, $this->workspaceSession, $this->access()));
    }

    /**
     * 自訂看板。
     *
     * @return ProjectBoards 看板用例
     */
    public function boards(): ProjectBoards
    {
        return $this->once('boards', fn () => new ProjectBoards($this->store, $this->workspaceSession, $this->access()));
    }

    /**
     * Project成員ACL。
     *
     * @return ProjectMembers 成員用例
     */
    public function members(): ProjectMembers
    {
        return $this->once('members', fn () => new ProjectMembers($this->store, $this->workspaceSession, $this->access()));
    }

    /**
     * 舊整包Workspace讀取（僅相容測試／工具）。
     *
     * @return WorkspaceQuery 整包查詢
     */
    public function query(): WorkspaceQuery
    {
        return $this->once('query', fn () => new WorkspaceQuery($this->store, $this->workspaceSession, $this->access(), fn () => $this->boards()));
    }

    /**
     * 工作區工時分析。
     *
     * @return WorkspaceTimeReport 報表用例
     */
    public function timeReport(): WorkspaceTimeReport
    {
        return $this->once('timeReport', fn () => new WorkspaceTimeReport($this->timeReports));
    }

    /**
     * commit後的Project變更訊號。
     *
     * @return ProjectSignals 訊號派送
     */
    public function signals(): ProjectSignals
    {
        return $this->once('signals', fn () => new ProjectSignals($this->workspaceSession, $this->projects));
    }
}
