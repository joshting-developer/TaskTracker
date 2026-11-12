<?php

namespace App\Http;

use App\Data\WorkspaceContext;
use App\Database\WorkspaceSqliteConnection;
use App\Database\WriteLocks;
use App\Domain\DomainError;
use App\Http\Session\SessionLeaseGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 在交易內重驗Session身分與工作區、比對X-Workspace-ID後才執行用例；寫入模式先鎖User再鎖Workspace。
 */
class WorkspaceTransaction
{
    /**
     * 注入HTTP身分邊界與鎖。
     *
     * @param  WorkspaceSession  $session  由Laravel Session重驗可信資格
     * @param  WriteLocks  $locks  User→Workspace寫入鎖
     * @param  SessionLeaseGuard  $lease  Session租期檢查
     */
    public function __construct(private WorkspaceSession $session, private WriteLocks $locks, private SessionLeaseGuard $lease) {}

    /**
     * 唯讀查詢：不取寫入鎖，SQLite使用唯讀交易。
     *
     * @template T
     *
     * @param  Request  $request  HTTP身分及租戶標頭
     * @param  Closure(WorkspaceContext): T  $use  接收交易內重驗資格的用例
     * @return T 用例結果
     *
     * @throws DomainError 未登入、資格失效或其他分頁已切換工作區
     */
    public function read(Request $request, Closure $use): mixed
    {
        return $this->run($request, WorkspaceAccessMode::Read, $use);
    }

    /**
     * 寫入：先取User→Workspace鎖再重驗資格。
     *
     * @template T
     *
     * @param  Request  $request  HTTP身分及租戶標頭
     * @param  Closure(WorkspaceContext): T  $use  接收交易內重驗資格的用例
     * @return T 用例結果
     *
     * @throws DomainError 未登入、資格失效或其他分頁已切換工作區
     */
    public function write(Request $request, Closure $use): mixed
    {
        return $this->run($request, WorkspaceAccessMode::Write, $use);
    }

    /**
     * 依明確模式開交易、取鎖、重驗資格並比對租戶標頭。
     *
     * @template T
     *
     * @param  Request  $request  HTTP身分及租戶標頭
     * @param  WorkspaceAccessMode  $mode  存取模式
     * @param  Closure(WorkspaceContext): T  $use  接收交易內重驗資格的用例
     * @param  bool  $verifyTenantHeader  是否比對X-Workspace-ID；只有舊整包讀取在切換後重載時不比對
     * @return T 用例結果
     *
     * @throws DomainError 未登入、資格失效或其他分頁已切換工作區
     */
    public function run(Request $request, WorkspaceAccessMode $mode, Closure $use, bool $verifyTenantHeader = true): mixed
    {
        $userId = $request->user()?->getAuthIdentifier();
        $workspaceId = $request->session()->get('workspace_id');
        $execute = function () use ($request, $mode, $use, $verifyTenantHeader, $userId, $workspaceId) {
            if ($mode === WorkspaceAccessMode::Write) {
                $this->locks->account($userId);
                $this->locks->workspace($workspaceId);
            }
            $context = $this->session->verified($request);
            $expectedWorkspace = $request->header('X-Workspace-ID');
            if ($verifyTenantHeader && $expectedWorkspace !== null && $expectedWorkspace !== $context->workspaceId) {
                DomainError::fail('WORKSPACE_CHANGED', '工作空間已在其他分頁切換，請重新載入後操作');
            }

            return $use($context);
        };
        $connection = DB::connection();
        if ($connection instanceof WorkspaceSqliteConnection && $mode === WorkspaceAccessMode::Read) {
            return $connection->readTransaction(fn () => $this->lease->around($request, $execute));
        }

        return $connection->transaction(fn () => $this->lease->around($request, $execute));
    }
}
