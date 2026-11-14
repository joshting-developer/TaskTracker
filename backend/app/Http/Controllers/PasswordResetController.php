<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordResetRequest;
use App\Http\Resources\ApiResponse;
use App\Services\PasswordResetService;
use Illuminate\Support\Facades\Auth;

/** CSRF保護的帳號恢復入口, 不替使用者建立登入 */
class PasswordResetController extends Controller
{
    /**
     * 注入密碼恢復用例
     *
     * @param  PasswordResetService  $resets  重設用例
     */
    public function __construct(private PasswordResetService $resets) {}

    /**
     * 驗證並正規化信箱, 回傳不透露帳號存在的申請結果
     *
     * @param  PasswordResetRequest  $request  匿名Email輸入
     * @return ApiResponse 固定申請結果
     */
    public function request(PasswordResetRequest $request): ApiResponse
    {
        return new ApiResponse($this->resets->request(mb_strtolower(trim($request->validated('email')))), true);
    }

    /**
     * 消耗重設連結並清除目前瀏覽器的舊登入
     *
     * @param  PasswordResetRequest  $request  一次性token與新密碼
     * @return ApiResponse 重設結果, 不自動登入
     */
    public function reset(PasswordResetRequest $request): ApiResponse
    {
        $input = $request->validated();
        $result = $this->resets->reset(mb_strtolower(trim($input['email'])), $input['token'], $input['password']);
        // The submitting browser must not save its old authenticated session again.
        if ($request->user()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new ApiResponse($result, true);
    }
}
