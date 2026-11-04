<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Services\ApiExecution;
use Illuminate\Http\Request;

/**
 * 處理註冊、登入與Session查閱的HTTP邊界。
 */
class AuthController extends Controller
{
    /**
     * 驗證註冊或邀請後建立帳號並更新Session。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function register(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();

        return new ApiResponse($execution->authenticate($request, 'register', $input), true);
    }

    /**
     * 驗證帳號並在必要時升級舊密碼雜湊。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function login(JsonOperationRequest $request, ApiExecution $execution): ApiResponse
    {
        $input = $request->payload();

        return new ApiResponse($execution->authenticate($request, 'login', $input), true);
    }

    /**
     * 回傳目前登入的工作空間身分。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function current(Request $request, ApiExecution $execution): ApiResponse
    {
        return new ApiResponse($execution->authenticate($request, 'current'), true);
    }

    /**
     * 結束Session並輪替CSRF token。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  ApiExecution  $execution  負責交易與Session重驗的API協調服務
     * @return ApiResponse
     */
    public function logout(Request $request, ApiExecution $execution): ApiResponse
    {
        return new ApiResponse($execution->authenticate($request, 'logout'), true);
    }
}
