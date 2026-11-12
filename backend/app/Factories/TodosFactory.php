<?php

namespace App\Factories;

use App\Services\RequestReplay;
use App\Services\Todos;
use App\Services\WorkspaceService;

/**
 * 依交易內重驗的Session建立個人待辦用例；本身不保存租戶狀態。
 */
class TodosFactory
{
    /**
     * 注入待辦用例的穩定協作者。
     *
     * @param  WorkspaceService  $workspaces  卡片寫入入口
     * @param  WorkspaceServicesFactory  $services  依可信Session建立限定Store與ProjectAccess
     * @param  RequestReplay  $replay  防重複提交與原結果重播
     */
    public function __construct(private WorkspaceService $workspaces, private WorkspaceServicesFactory $services, private RequestReplay $replay) {}

    /**
     * 建立本次操作專用的待辦用例。
     *
     * @param  array<string,mixed>  $workspaceSession  交易內由Session重驗的身分、工作區與權限
     * @return Todos 待辦用例
     */
    public function forSession(array $workspaceSession): Todos
    {
        $services = $this->services->forSession($workspaceSession);

        return new Todos($workspaceSession, $services->store, $services->access(), $this->replay, $this->workspaces);
    }
}
