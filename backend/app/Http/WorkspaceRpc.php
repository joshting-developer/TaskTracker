<?php

namespace App\Http;

use App\Authorization\WorkspacePermissions;
use App\Data\TimeReportData;
use App\Data\WorkspaceContext;
use App\Data\Writes\RpcWriteData;
use App\Domain\DomainError;
use App\Factories\AccountSettingsFactory;
use App\Factories\TodosFactory;
use App\Factories\WorkspaceServicesFactory;
use App\Services\AccountService;
use App\Services\AccountSettings;
use App\Services\SmtpService;
use App\Services\Todos;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;

/**
 * 舊RPC分派：依操作名稱宣告存取模式，交給WorkspaceTransaction開交易與重驗資格後，再轉給對應用例。
 */
class WorkspaceRpc
{
    /** 不取寫入鎖的舊RPC讀取；其餘操作一律Write。 */
    private const MODES = [
        'getWorkspace' => WorkspaceAccessMode::Read,
        'getTimeReport' => WorkspaceAccessMode::Read,
        'getTodos' => WorkspaceAccessMode::Unlocked,
        'getProfile' => WorkspaceAccessMode::Unlocked,
        'getProjectMembers' => WorkspaceAccessMode::Unlocked,
        'smtpStatus' => WorkspaceAccessMode::Unlocked,
        'verifySmtp' => WorkspaceAccessMode::Unlocked,
    ];

    /**
     * 注入交易邊界與各RPC用例。
     *
     * @param  WorkspaceTransaction  $transaction  交易內重驗身分與租戶
     * @param  AccountService  $accounts  成員邀請
     * @param  SmtpService  $smtp  SMTP狀態與驗證
     * @param  AccountSettingsFactory  $settings  依操作者建立設定用例
     * @param  TodosFactory  $todos  依操作者建立待辦用例
     * @param  WorkspaceService  $workspaces  Project／卡片／工時／看板／成員RPC
     * @param  WorkspaceServicesFactory  $services  單次操作服務組
     */
    public function __construct(private WorkspaceTransaction $transaction, private AccountService $accounts, private SmtpService $smtp, private AccountSettingsFactory $settings, private TodosFactory $todos, private WorkspaceService $workspaces, private WorkspaceServicesFactory $services) {}

    /**
     * 接收各領域Request建立的型別資料，沿用原交易與業務入口。
     *
     * @param  Request  $request  可信Session邊界
     * @param  RpcWriteData  $data  專用Request建立的輸入
     * @return array<string,mixed> 有界寫入結果或歷史receipt原結果
     */
    public function executeData(Request $request, RpcWriteData $data): array
    {
        return $this->execute($request, $data->operation, $data->input());
    }

    /**
     * 執行舊RPC操作。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  string  $operation  已允許的操作名稱
     * @param  array<string,mixed>  $input  此次操作的原始輸入欄位
     * @return array<string,mixed> 操作結果
     *
     * @throws DomainError 資格、權限、版本或識別碼衝突
     */
    public function execute(Request $request, string $operation, array $input): array
    {
        $mode = self::MODES[$operation] ?? WorkspaceAccessMode::Write;

        return $this->transaction->run($request, $mode, function (WorkspaceContext $context) use ($operation, $input) {
            $session = $context->legacyIdentity();
            if (in_array($operation, AccountSettings::OPERATIONS, true)) {
                return $this->settings->forActor($session)->execute($operation, $input);
            }
            if (in_array($operation, Todos::OPERATIONS, true)) {
                return $this->todos->forSession($session)->execute($operation, $input);
            }
            if ($operation === 'inviteMember') {
                return $this->accounts->invite($session, $input);
            }
            if (in_array($operation, ['smtpStatus', 'verifySmtp'], true)) {
                WorkspacePermissions::authorize($session, 'smtp.verify');

                return $operation === 'smtpStatus' ? $this->smtp->status() : $this->smtp->verify();
            }

            return $this->workspaces->execute($session, $operation, $input);
        }, $operation !== 'getWorkspace');
    }

    /**
     * 工作區工時分析（唯讀交易）。
     *
     * @param  Request  $request  HTTP身分及租戶標頭
     * @param  TimeReportData  $filters  已驗證條件
     * @return array<string,mixed> 報表結果
     *
     * @throws DomainError 資格或權限不足
     */
    public function timeReport(Request $request, TimeReportData $filters): array
    {
        return $this->transaction->read($request, fn (WorkspaceContext $context) => $this->services->forSession($context->legacyIdentity())->timeReport()->get($context->legacyIdentity(), $filters));
    }
}
