<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use App\Http\Resources\ApiResponse;
use App\Services\AdminAuthenticationService;
use Illuminate\Http\Request;

/** 獨立管理Session的HTTP生命週期, 不登入一般User guard或授予Workspace權限 */
class AdminSessionsController extends Controller
{
    /** 注入管理認證用例, Session僅在當次HTTP方法使用
     *
     * @param  AdminAuthenticationService  $authentication  管理帳密及資格重驗
     */
    public function __construct(private AdminAuthenticationService $authentication) {}

    /**
     * 驗密碼後輪替Session ID與CSRF, 一般帳號狀態保留
     *
     * @param  AdminLoginRequest  $request  CSRF保護的當次輸入
     * @return ApiResponse 已驗證的管理名稱
     */
    public function store(AdminLoginRequest $request): ApiResponse
    {
        $claim = $this->authentication->authenticate($request->toDto());
        $request->session()->regenerate(true);
        $request->session()->put('platform_admin', $claim);

        return new ApiResponse(['username' => $this->authentication->username()], true);
    }

    /**
     * 不接受Owner或PAT作管理資格
     *
     * @param  Request  $request  伺服器Session
     * @return ApiResponse 目前管理名稱
     */
    public function show(Request $request): ApiResponse
    {
        $this->authentication->assertCurrent($request->session()->get('platform_admin'));

        return new ApiResponse(['username' => $this->authentication->username()], true);
    }

    /**
     * 只移除管理資格並換Session ID, 不替一般帳號登出
     *
     * @param  Request  $request  CSRF保護的伺服器Session
     * @return ApiResponse 空成功回應
     */
    public function destroy(Request $request): ApiResponse
    {
        $request->session()->forget('platform_admin');
        $request->session()->regenerate(true);

        return new ApiResponse(null, true);
    }
}
