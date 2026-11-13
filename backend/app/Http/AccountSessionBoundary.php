<?php

namespace App\Http;

use App\Data\AuthenticatedAccountData;
use App\Database\WriteLocks;
use App\Domain\DomainError;
use App\Http\Session\SessionLeaseGuard;
use App\Services\AccountService;
use App\Services\EmailVerificationService;
use App\Services\WorkspaceMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 帳號與Session邊界：註冊、登入、登出、目前身分、工作區清單／切換與邀請，在交易內取鎖後才改Session。
 */
class AccountSessionBoundary
{
    /**
     * 注入帳號資格、工作區資格、鎖與Session邊界。
     *
     * @param  AccountService  $accounts  帳號與工作空間資格
     * @param  WorkspaceMemberships  $memberships  工作區清單、切換與邀請
     * @param  WorkspaceSession  $session  由Laravel Session重驗可信資格
     * @param  WriteLocks  $locks  驗證流程的帳號鎖
     * @param  SessionLeaseGuard  $lease  Session租期檢查
     * @param  EmailVerificationService  $verification  信箱驗證
     */
    public function __construct(private AccountService $accounts, private WorkspaceMemberships $memberships, private WorkspaceSession $session, private WriteLocks $locks, private SessionLeaseGuard $lease, private EmailVerificationService $verification) {}

    /**
     * 執行帳號／Session操作，回傳目前身分；登出回傳null。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  string  $operation  register、login、current、logout、invitation、workspaces、switchWorkspace或acceptWorkspaceInvite
     * @param  array<string,mixed>  $input  此次操作的原始輸入欄位
     * @return array<string,mixed>|null 目前身分、工作區清單或邀請預覽；登出時為null
     *
     * @throws DomainError 未登入、帳密錯誤、資格不符或未知操作
     */
    public function handle(Request $request, string $operation, array $input = []): ?array
    {
        $userId = $request->user()?->getAuthIdentifier();

        $authenticate = function () use ($request, $operation, $input, $userId) {
            $this->locks->authentication($operation, $input, $userId);
            if ($operation === 'logout') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return null;
            }
            if ($operation === 'invitation') {
                return $this->memberships->preview($input);
            }
            if (in_array($operation, ['workspaces', 'switchWorkspace', 'acceptWorkspaceInvite'], true)) {
                $user = $this->accounts->freshUser($request->user()?->getAuthIdentifier());
                if (! $user) {
                    DomainError::fail('UNAUTHENTICATED', '請先登入');
                }
                if ($operation === 'workspaces') {
                    return ['workspaces' => $this->memberships->listing($user)];
                }
                $workspaceId = $operation === 'acceptWorkspaceInvite' ? $this->memberships->accept($user, $input) : $this->memberships->target($user, $input);
                $request->session()->put('workspace_id', $workspaceId);

                return $this->session->context($request);
            }

            return match ($operation) {
                'current' => $request->session()->has('pending_user_id') ? $this->verification->status($request->session()->get('pending_user_id')) : [...$this->session->context($request), 'features' => $this->features()],
                'register' => $this->establishSession($request, $this->accounts->register($input)),
                'login' => $this->establishSession($request, $this->accounts->login($input)),
                default => DomainError::fail('NOT_FOUND', '找不到這個操作'),
            };
        };

        $result = DB::transaction(fn () => $this->lease->around($request, $authenticate));

        // afterCommit同步寄送完成後才取安全狀態, 不將SMTP IO放進帳號交易。
        return $operation === 'register' && ! empty($result['verificationRequired']) ? $this->verification->status($request->session()->get('pending_user_id')) : $result;
    }

    /**
     * 依執行環境決定的介面開關；TT-314起通知預覽只在local／testing顯示，正式環境等通知完善再開放。
     *
     * @return array{notificationPreview: bool} 前端依此隱藏尚未開放的功能入口
     */
    private function features(): array
    {
        return ['notificationPreview' => in_array(config('app.env'), ['local', 'testing'], true)];
    }

    /**
     * 登入成功後換新Session ID並記錄工作區，避免Session fixation。
     *
     * @param  Request  $request  目前HTTP與Session
     * @param  AuthenticatedAccountData  $account  領域服務驗證的帳號與空間
     * @return array<string,mixed> 重驗後的目前身分
     */
    private function establishSession(Request $request, AuthenticatedAccountData $account): array
    {
        if ($account->user->email_verification_required && ! $account->user->email_verified_at) {
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->forget('workspace_id');
            $request->session()->put('pending_user_id', $account->user->id);

            return $this->verification->status($account->user->id);
        }
        $request->session()->forget('pending_user_id');
        Auth::login($account->user);
        $request->session()->regenerate();
        $request->session()->put('workspace_id', $account->workspaceId);

        return $this->session->context($request);
    }
}
