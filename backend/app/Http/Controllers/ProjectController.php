<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供專案建立、資訊更新及狀態設定HTTP入口。
 */
class ProjectController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceRpc  $rpc  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceRpc $rpc) {}

    /**
     * 驗證JSON後建立專案，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function store(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'createProject', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後更新專案資訊，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function update(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'updateProject', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後更新自訂狀態，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function statuses(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'updateStatuses', $input);

        return new ApiResponse($result);
    }
}
