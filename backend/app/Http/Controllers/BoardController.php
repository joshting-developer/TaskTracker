<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\ApiExecution;

/**
 * 提供看板建立、收錄更新與移除HTTP入口。
 */
class BoardController extends Controller
{
    /**
     * 驗證JSON後建立自訂看板，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function store(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'createBoard', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後更新看板收錄，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function update(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'updateBoard', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後移除看板並保留任務，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function archive(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'archiveBoard', $input);

        return new ApiResponse($result);
    }
}
