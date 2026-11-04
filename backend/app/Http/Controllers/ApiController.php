<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Services\ApiExecution;

/**
 * 處理未知或已停用操作，保留舊API錯誤及登入契約。
 */
class ApiController extends Controller
{
    /**
     * 對未知或已停用操作套用一致的登入與JSON錯誤契約。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  string  $operation  已允許的操作名稱
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function unknown(JsonOperationRequest $request, string $operation, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();

        return new ApiResponse($execution->execute($request, $operation, $input));
    }
}
