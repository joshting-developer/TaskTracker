<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\ApiExecution;

/**
 * 提供工作空間快照、邀請與示範資料HTTP入口。
 */
class WorkspaceController extends Controller
{
    /**
     * 驗證JSON後查閱可見工作空間快照，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function show(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'getWorkspace', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後邀請同事加入工作空間，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function invite(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'inviteMember', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後載入示範專案，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function demo(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'loadDemo', $input);

        return new ApiResponse($result);
    }
}
