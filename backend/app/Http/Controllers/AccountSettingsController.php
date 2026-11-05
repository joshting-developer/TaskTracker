<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Services\ApiExecution;

/** 提供已註冊設定操作的JSON邊界，授權及交易交由服務處理。 */
class AccountSettingsController extends Controller
{
    /** 執行路由固定的設定動作，不採用body自稱的操作或身分。
     * @param  JsonOperationRequest  $request  JSON請求
     * @param  ApiExecution  $execution  可信Session及交易協調
     * @param  string  $operation  路由預設的固定操作
     * @return ApiResponse
     */
    public function execute(JsonOperationRequest $request, ApiExecution $execution, string $operation): ApiResponse
    {
        return new ApiResponse($execution->execute($request, $operation, $request->payload()));
    }
}
