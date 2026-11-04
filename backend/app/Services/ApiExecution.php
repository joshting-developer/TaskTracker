<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Domain\DomainError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 協調HTTP Session、交易與既有操作契約，業務規則交由領域服務。
 */
class ApiExecution
{
    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  AccountService  $accounts  帳號與工作空間資格服務
     * @param  SmtpService  $smtp  SMTP驗證服務
     * @return void
     */
    public function __construct(private AccountService $accounts, private SmtpService $smtp) {}

    /**
     * 在同一交易處理帳號與Session異動。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  string  $operation  已允許的操作名稱
     * @param  array  $input  此次操作的原始輸入欄位
     * @return ?array
     */
    public function authenticate(Request $request, string $operation, array $input = []): ?array
    {
        return DB::transaction(function () use ($request, $operation, $input) {
            if ($operation === 'logout') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return null;
            }

            return match ($operation) {
                'current' => $this->accounts->context($request),
                'register' => $this->accounts->register($request, $input),
                'login' => $this->accounts->login($request, $input),
                default => DomainError::fail('NOT_FOUND', '找不到這個操作'),
            };
        });
    }

    /**
     * 驗證操作邊界並執行查詢或受保護的寫入。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  string  $operation  已允許的操作名稱
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function execute(Request $request, string $operation, array $input): array
    {
        return DB::transaction(function () use ($request, $operation, $input) {
            $workspaceSession = $this->accounts->context($request);
            if (in_array($operation, Todos::OPERATIONS, true)) {
                return (new Todos($workspaceSession))->execute($operation, $input);
            }
            if ($operation === 'inviteMember') {
                return $this->accounts->invite($workspaceSession, $input);
            }
            if (in_array($operation, ['smtpStatus', 'verifySmtp'], true)) {
                WorkspacePermissions::authorize($workspaceSession, 'smtp.verify');

                return $operation === 'smtpStatus' ? $this->smtp->status() : $this->smtp->verify();
            }

            return (new WorkspaceService($workspaceSession))->execute($operation, $input);
        });
    }
}
