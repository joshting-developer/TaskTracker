<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\ApiExecution;

/**
 * 提供本人工時建立、修改、作廢及工作空間分析HTTP入口。
 */
class TimeEntryController extends Controller
{
    /**
     * 驗證JSON並查詢工作空間工時分析，授權與彙整交由領域服務。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  交易與Session重驗入口
     * @return ApiResponse 工作空間報表
     */
    public function report(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'getTimeReport', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後填報本人工時，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function store(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'logTime', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後修改本人工時，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function update(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'updateTime', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後作廢本人工時，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function invalidate(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'voidTime', $input);

        return new ApiResponse($result);
    }
}
