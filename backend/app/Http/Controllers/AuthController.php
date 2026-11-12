<?php

namespace App\Http\Controllers;

use App\Http\AccountSessionBoundary;
use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use Illuminate\Http\Request;

/**
 * 處理註冊、登入與Session查閱的HTTP邊界。
 */
class AuthController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  AccountSessionBoundary  $accounts  此Controller的穩定協作者
     */
    public function __construct(private AccountSessionBoundary $accounts) {}

    /**
     * 驗證註冊或邀請後建立帳號並更新Session。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function register(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();

        return new ApiResponse($this->accounts->handle($request, 'register', $input), true);
    }

    /**
     * 驗證帳號並在必要時升級舊密碼雜湊。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function login(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();

        return new ApiResponse($this->accounts->handle($request, 'login', $input), true);
    }

    /**
     * 回傳目前登入的工作空間身分。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function current(Request $request): ApiResponse
    {
        return new ApiResponse($this->accounts->handle($request, 'current'), true);
    }

    /**
     * 結束Session並輪替CSRF token。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function logout(Request $request): ApiResponse
    {
        return new ApiResponse($this->accounts->handle($request, 'logout'), true);
    }

    /**
     * 處理工作區清單、切換與邀請，資格由Session及單次token驗證。
     *
     * @param  JsonOperationRequest  $request  JSON輸入及登入Session
     * @param  string  $operation  固定路由操作
     * @return ApiResponse 公開資格或邀請資訊
     */
    public function workspace(JsonOperationRequest $request, string $operation): ApiResponse
    {
        return new ApiResponse($this->accounts->handle($request, $operation, $request->payload()), true);
    }
}
