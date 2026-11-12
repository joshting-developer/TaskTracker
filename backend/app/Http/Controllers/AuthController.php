<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\AccountSessionBoundary;
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
     * @param  AccountSessionBoundary  $accounts  帳號與Session邊界
     * @return ApiResponse
     */
    public function register(JsonOperationRequest $request, AccountSessionBoundary $accounts): ApiResponse
    {
        $input = $request->payload();

        return new ApiResponse($accounts->handle($request, 'register', $input), true);
    }

    /**
     * 驗證帳號並在必要時升級舊密碼雜湊。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  AccountSessionBoundary  $accounts  帳號與Session邊界
     * @return ApiResponse
     */
    public function login(JsonOperationRequest $request, AccountSessionBoundary $accounts): ApiResponse
    {
        $input = $request->payload();

        return new ApiResponse($accounts->handle($request, 'login', $input), true);
    }

    /**
     * 回傳目前登入的工作空間身分。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  AccountSessionBoundary  $accounts  帳號與Session邊界
     * @return ApiResponse
     */
    public function current(Request $request, AccountSessionBoundary $accounts): ApiResponse
    {
        return new ApiResponse($accounts->handle($request, 'current'), true);
    }

    /**
     * 結束Session並輪替CSRF token。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  AccountSessionBoundary  $accounts  帳號與Session邊界
     * @return ApiResponse
     */
    public function logout(Request $request, AccountSessionBoundary $accounts): ApiResponse
    {
        return new ApiResponse($accounts->handle($request, 'logout'), true);
    }

    /**
     * 處理工作區清單、切換與邀請，資格由Session及單次token驗證。
     *
     * @param  JsonOperationRequest  $request  JSON輸入及登入Session
     * @param  string  $operation  固定路由操作
     * @param  AccountSessionBoundary  $accounts  帳號與Session邊界
     * @return ApiResponse 公開資格或邀請資訊
     */
    public function workspace(JsonOperationRequest $request, string $operation, AccountSessionBoundary $accounts): ApiResponse
    {
        return new ApiResponse($accounts->handle($request, $operation, $request->payload()), true);
    }
}
