<?php

namespace App\Http\Controllers;

use App\Http\AccountSessionBoundary;
use App\Http\Requests\Writes\SettingsWriteRequest;
use App\Http\Resources\ApiResponse;

/** 不需要Workspace的本人設定, 沿用帳號Session／CSRF交易 */
class AccountProfileController extends Controller
{
    /**
     * 注入帳號用例邊界
     *
     * @param  AccountSessionBoundary  $accounts  本人資格重驗與交易
     */
    public function __construct(private AccountSessionBoundary $accounts) {}

    /**
     * 固定路由操作, 不接受輸入指定操作者
     *
     * @param  SettingsWriteRequest  $request  型別化本人設定輸入
     * @param  string  $operation  由路由固定的getProfile或updateProfile
     * @return ApiResponse 本人資料或最小更新結果
     */
    public function execute(SettingsWriteRequest $request, string $operation): ApiResponse
    {
        return new ApiResponse($this->accounts->handle($request, $operation, $request->toDto()->input()), true);
    }
}
