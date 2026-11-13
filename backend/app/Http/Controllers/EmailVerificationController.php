<?php

namespace App\Http\Controllers;

use App\Domain\DomainError;
use App\Http\Requests\EmailVerificationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceSession;
use App\Services\EmailVerificationService;
use Illuminate\Support\Facades\Auth;

/** pending Session與一次性連結的HTTP邊界, GET沒有啟用副作用 */
class EmailVerificationController extends Controller
{
    /** 注入驗證用例與HTTP工作區資格邊界
     * @param  EmailVerificationService  $verification  驗證用例
     * @param  WorkspaceSession  $session  已驗證後的工作區資格
     */
    public function __construct(private EmailVerificationService $verification, private WorkspaceSession $session) {}

    /** 驗證一次性憑證並建立已驗證資格
     * @param  EmailVerificationRequest  $request  CSRF保護的驗證輸入
     * @return ApiResponse 啟用後身分
     */
    public function verify(EmailVerificationRequest $request): ApiResponse
    {
        $input = $request->validated();
        $account = $this->verification->verify($request->session()->get('pending_user_id'), $input['code'] ?? '', $input['token'] ?? '');
        Auth::login($account->user);
        $request->session()->regenerate();
        $request->session()->forget('pending_user_id');
        $request->session()->put('workspace_id', $account->workspaceId);

        return new ApiResponse($this->session->context($request), true);
    }

    /** 重驗冷卻與地址後撤銷舊事件並重寄
     * @param  EmailVerificationRequest  $request  pending Session及選填新信箱
     * @return ApiResponse 新驗證資訊
     */
    public function resend(EmailVerificationRequest $request): ApiResponse
    {
        $id = $request->session()->get('pending_user_id');
        if (! is_string($id)) {
            DomainError::fail('UNAUTHENTICATED', '請重新登入');
        }

        return new ApiResponse($this->verification->resend($id, $request->validated('email')), true);
    }
}
