<?php

namespace App\Http\Controllers;

use App\Http\WorkspaceRpc;
use App\Http\Requests\JsonOperationRequest;
use App\Http\Requests\TimeReportRequest;
use App\Http\Resources\ApiResponse;

/**
 * 提供本人工時建立、修改、作廢及工作空間分析HTTP入口。
 */
class TimeEntryController extends Controller
{
    /**
     * 驗證JSON並查詢工作空間工時分析，授權與彙整交由領域服務。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse 工作空間報表
     */
    public function report(TimeReportRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $result = $rpc->timeReport($request, $request->toDto());

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後填報本人工時，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function store(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'logTime', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後修改本人工時，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function update(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'updateTime', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後作廢本人工時，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function invalidate(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'voidTime', $input);

        return new ApiResponse($result);
    }
}
