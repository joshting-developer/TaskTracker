<?php

namespace App\Http;

use App\Authorization\WorkspacePermissions;
use App\Data\AuthenticatedAccountData;
use App\Data\TimeReportData;
use App\Database\WorkspaceSqliteConnection;
use App\Database\WriteLocks;
use App\Domain\DomainError;
use App\Factories\AccountSettingsFactory;
use App\Factories\TodosFactory;
use App\Factories\WorkspaceServicesFactory;
use App\Http\Session\SessionLease;
use App\Services\AccountService;
use App\Services\AccountSettings;
use App\Services\SmtpService;
use App\Services\Todos;
use App\Services\WorkspaceMemberships;
use App\Services\WorkspaceService;
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
     * @param  WorkspaceSession  $session  HTTP身分邊界
     * @param  AccountSettingsFactory  $settings  當次設定用例組裝邊界
     * @param  WriteLocks  $locks  User→Workspace寫入鎖
     * @param  WorkspaceMemberships  $memberships  工作區資格與邀請
     * @param  WorkspaceService  $workspaces  Workspace RPC用例（操作者為執行參數）
     * @param  WorkspaceServicesFactory  $services  依可信Session建立單次操作服務
     * @param  TodosFactory  $todos  依可信Session建立待辦用例
     * @return void
     */
    public function __construct(private AccountService $accounts, private SmtpService $smtp, private WorkspaceSession $session, private AccountSettingsFactory $settings, private WriteLocks $locks, private WorkspaceMemberships $memberships, private WorkspaceService $workspaces, private WorkspaceServicesFactory $services, private TodosFactory $todos) {}

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
                $user = $request->user()?->fresh();
                if (! $user) {
                    DomainError::fail('UNAUTHENTICATED', '請先登入');
                }
                $memberships = $this->memberships;
                if ($operation === 'workspaces') {
                    return ['workspaces' => $memberships->listing($user)];
                }
                $workspaceId = $operation === 'acceptWorkspaceInvite' ? $memberships->accept($user, $input) : $memberships->target($user, $input);
                $request->session()->put('workspace_id', $workspaceId);

                return $this->session->context($request);
            }

            return match ($operation) {
                'current' => $this->session->context($request),
                'register' => $this->establishSession($request, $this->accounts->register($input)),
                'login' => $this->establishSession($request, $this->accounts->login($input)),
                default => DomainError::fail('NOT_FOUND', '找不到這個操作'),
            };
        };

        return DB::transaction(fn () => $this->guardSession($request, $authenticate));
    }

    /** 將已驗證帳號寫入HTTP Session，領域服務不接觸Request或Auth facade。
     * @param  Request  $request  目前HTTP與Session
     * @param  AuthenticatedAccountData  $account  領域服務驗證的帳號與空間
     * @return array 公開工作空間資格
     */
    private function establishSession(Request $request, AuthenticatedAccountData $account): array
    {
        Auth::login($account->user);
        $request->session()->regenerate();
        $request->session()->put('workspace_id', $account->workspaceId);

        return $this->session->context($request);
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
        return $this->inWorkspace($request, $operation, function ($workspaceSession) use ($operation, $input) {
            if (in_array($operation, AccountSettings::OPERATIONS, true)) {
                return $this->settings->forActor($workspaceSession)->execute($operation, $input);
            }
            if (in_array($operation, Todos::OPERATIONS, true)) {
                return $this->todos->forSession($workspaceSession)->execute($operation, $input);
            }
            if ($operation === 'inviteMember') {
                return $this->accounts->invite($workspaceSession, $input);
            }
            if (in_array($operation, ['smtpStatus', 'verifySmtp'], true)) {
                WorkspacePermissions::authorize($workspaceSession, 'smtp.verify');

                return $operation === 'smtpStatus' ? $this->smtp->status() : $this->smtp->verify();
            }

            return $this->workspaces->execute($workspaceSession, $operation, $input);
        });
    }

    /**
     * 專用唯讀用例接收一次建立的DTO，不經任意payload派送。
     *
     * @param  Request  $request  HTTP身分及租戶標頭
     * @param  TimeReportData  $filters  已驗證條件
     * @return array 已授權的工時報表
     */
    public function timeReport(Request $request, TimeReportData $filters): array
    {
        return $this->inWorkspace($request, 'getTimeReport', function ($context) use ($filters) {
            return $this->services->forSession($context)->timeReport()->get($filters);
        });
    }

    /** 有界查詢共用可信身分與交易，查詢服務不依賴HTTP或Session。 */
    public function query(Request $request, \Closure $read): array
    {
        return $this->inWorkspace($request, 'query', $read);
    }

    /**
     * 共用交易及Session／X-Workspace-ID驗證，寫入與專用查詢均不可略過。
     *
     * @param  Request  $request  HTTP身分及租戶標頭
     * @param  string  $operation  API操作名稱
     * @param  \Closure  $callback  接收可信資格的領域用例
     * @return array 用例結果
     */
    private function inWorkspace(Request $request, string $operation, \Closure $callback): array
    {
        $userId = $request->user()?->getAuthIdentifier();
        $workspaceId = $request->session()->get('workspace_id');
        $execute = function () use ($request, $operation, $callback, $userId, $workspaceId) {
            if (! in_array($operation, ['query', 'getWorkspace', 'getTimeReport', 'getTodos', 'getProfile', 'getProjectMembers', 'smtpStatus', 'verifySmtp'], true)) {
                $this->locks->account($userId);
                $this->locks->workspace($workspaceId);
            }
            $context = $this->session->context($request);
            $expectedWorkspace = $request->header('X-Workspace-ID');
            if ($operation !== 'getWorkspace' && $expectedWorkspace !== null && $expectedWorkspace !== $context['workspace']['id']) {
                DomainError::fail('WORKSPACE_CHANGED', '工作空間已在其他分頁切換，請重新載入後操作');
            }

            return $callback($context);
        };
        $connection = DB::connection();
        if ($connection instanceof WorkspaceSqliteConnection && in_array($operation, ['query', 'getWorkspace', 'getTimeReport'], true)) {
            return $connection->readTransaction(fn () => $this->guardSession($request, $execute));
        }

        return $connection->transaction(fn () => $this->guardSession($request, $execute));
    }

    /**
     * 在同一交易執行前後重驗Session鎖，斷線失鎖時rollback而非繼續提交。
     *
     * @param  Request  $request  持有當次鎖handle的HTTP
     * @param  \Closure  $operation  原有授權／版本／業務操作
     * @return mixed 原業務結果
     */
    private function guardSession(Request $request, \Closure $operation): mixed
    {
        $lease = $request->attributes->get(SessionLease::class);
        $lease?->assertHeld();
        $result = $operation();
        $lease?->assertHeld();

        return $result;
    }
}
