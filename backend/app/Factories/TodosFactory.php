<?php

namespace App\Factories;

use App\Services\Todos;
use App\Services\WorkspaceService;

/**
 * 依交易內重驗的Session建立個人待辦用例；本身不保存租戶狀態。
 */
class TodosFactory
{
    /**
     * 注入待辦轉卡沿用的RPC寫入入口。
     *
     * @param  WorkspaceService  $workspaces  卡片寫入入口
     */
    public function __construct(private WorkspaceService $workspaces) {}

    /**
     * 建立本次操作專用的待辦用例。
     *
     * @param  array<string,mixed>  $workspaceSession  交易內由Session重驗的身分、工作區與權限
     * @return Todos 待辦用例
     */
    public function forSession(array $workspaceSession): Todos
    {
        return new Todos($workspaceSession, $this->workspaces);
    }
}
