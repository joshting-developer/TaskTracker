<?php

namespace App\Http\Controllers;

use App\Data\WorkspaceContext;
use App\Http\Requests\Writes\WorkspaceMemberStatusRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceTransaction;
use App\Services\WorkspaceMemberStatusService;

/** 明確狀態變更入口, 不透過泛用角色寫入猜停用意圖 */
class WorkspaceMemberStatusController extends Controller
{
    /** 注入HTTP交易與資格用例
     * @param  WorkspaceTransaction  $workspace  鎖後重驗租戶及Session
     * @param  WorkspaceMemberStatusService  $members  資格變更服務
     */
    public function __construct(private WorkspaceTransaction $workspace, private WorkspaceMemberStatusService $members) {}

    /** 以可信身分執行狀態變更
     * @param  WorkspaceMemberStatusRequest  $request  已驗證傳輸
     * @return ApiResponse 最小結果
     */
    public function update(WorkspaceMemberStatusRequest $request): ApiResponse
    {
        return new ApiResponse($this->workspace->write($request, fn (WorkspaceContext $context) => $this->members->change($context, $request->payload())));
    }
}
