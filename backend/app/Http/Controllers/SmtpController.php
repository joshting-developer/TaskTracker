<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\ApiExecution;

/**
 * 提供SMTP狀態與連線驗證HTTP入口。
 */
class SmtpController extends Controller
{
    /**
     * 驗證JSON後查閱SMTP設定狀態，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function status(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'smtpStatus', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後驗證SMTP連線而不寄信，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function verify(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();
        $result = $execution->execute($request, 'verifySmtp', $input);

        return new ApiResponse($result);
    }
}
