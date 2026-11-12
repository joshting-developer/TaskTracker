<?php

namespace App\Http\Integrations;

use App\Data\TokenContext;
use App\Database\WorkspaceSqliteConnection;
use App\Database\WriteLocks;
use App\Domain\DomainError;
use App\Factories\ProjectAccessFactory;
use App\Factories\WorkspaceContextFactory;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Integrations\IntegrationAudits;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** 純Bearer交易及來源邊界；每次重新驗證，不使用Auth guard已快取的操作者。 */
class BearerTokenSession
{
    /** 注入現行鎖／資格／Project授權與來源紀錄，不複製業務權限映射。 */
    public function __construct(private WorkspaceContextFactory $contexts, private WriteLocks $locks, private ProjectAccessFactory $projects, private IntegrationAudits $audits) {}

    /**
     * 以PAT回傳最小身分，既有Session與X-Workspace-ID不改變Token綁定。
     *
     * @param  Request  $request  僅取Authorization header
     * @return array{userId:string,workspaceId:string,abilities:array} 安全身分投影
     */
    public function identity(Request $request): array
    {
        return $this->read($request, fn (TokenContext $token) => ['userId' => $token->workspace->userId,
            'workspaceId' => $token->workspace->workspaceId, 'abilities' => $token->abilities]);
    }

    /**
     * 讀取Token Scope及當下資格的交集，若指定Project則沿用集中permission檢查。
     *
     * @template T
     *
     * @param  Request  $request  Bearer請求
     * @param  Closure(TokenContext):T  $use  已授權用例；仍負責資源／作者條件
     * @param  string|null  $projectId  已解析的目標Project公開ID
     * @param  string  $permission  伺服器指定的Project permission
     * @return T 用例結果，不追加讀取稽核
     */
    public function read(Request $request, Closure $use, ?string $projectId = null, string $permission = 'issue.read'): mixed
    {
        return $this->run($request, false, $use, $projectId, $permission);
    }

    /**
     * 鎖後重驗Token／資格及Project permission，成功結果與來源紀錄同交易提交。
     *
     * @template T
     *
     * @param  Request  $request  Bearer請求
     * @param  string  $operation  伺服器固定operationId
     * @param  Closure(TokenContext):T  $use  業務用例，共用既有Service及作者／revision／requestId規則
     * @param  string|null  $projectId  目標Project公開ID，非全工作區放行
     * @param  string  $permission  伺服器指定的Project寫入permission
     * @param  string|null  $resourceId  已授權資源識別
     * @return T 與來源紀錄原子提交的用例結果
     */
    public function write(Request $request, string $operation, Closure $use, ?string $projectId = null, string $permission = 'issue.write', ?string $resourceId = null): mixed
    {
        return $this->run($request, true, function (TokenContext $token) use ($operation, $use, $resourceId) {
            $result = $use($token);
            $this->audits->append($token->workspace, $operation, $resourceId, $token);

            return $result;
        }, $projectId, $permission);
    }

    /**
     * hint只供鎖定；鎖後才建立讀取快照，不能拿鎖前Token或guard快取作授權。
     *
     * @template T
     *
     * @param  Request  $request  Bearer header
     * @param  bool  $write  是否需User→Workspace鎖
     * @param  Closure(TokenContext):T  $use  單次用例
     * @param  string|null  $projectId  目標Project
     * @param  string  $permission  有效permission ID
     * @return T 用例結果
     */
    private function run(Request $request, bool $write, Closure $use, ?string $projectId, string $permission): mixed
    {
        $plain = $request->bearerToken();
        $hint = $write && $plain ? PersonalAccessToken::findToken($plain) : null;
        if (! $plain || ($write && (! $hint || $hint->tokenable_type !== User::class))) {
            DomainError::fail('UNAUTHENTICATED', 'Token無效、已過期或已撤銷');
        }
        $workspaceId = $hint ? Workspace::whereKey($hint->workspace_id)->value('public_id') : null;
        $execute = function () use ($plain, $hint, $workspaceId, $write, $use, $projectId, $permission) {
            if ($write) {
                $this->locks->account($hint->tokenable_id);
                $this->locks->workspace($workspaceId);
            }
            $token = PersonalAccessToken::findToken($plain);
            if (! $token || $token->tokenable_type !== User::class || $token->revoked_at !== null || ! $token->expires_at || $token->expires_at->isPast()
                || ($hint && ($token->tokenable_id !== $hint->tokenable_id || $token->workspace_id !== $hint->workspace_id))) {
                DomainError::fail('UNAUTHENTICATED', 'Token無效、已過期或已撤銷');
            }
            $currentWorkspaceId = $write ? $workspaceId : Workspace::whereKey($token->workspace_id)->value('public_id');
            $workspace = $this->contexts->verify(User::find($token->tokenable_id), $currentWorkspaceId);
            if (! $token->can($write ? 'write' : 'read')) {
                DomainError::fail('FORBIDDEN', 'Token未授權此操作');
            }
            if ($projectId !== null) {
                $this->projects->forContext($workspace)->require($projectId, $permission);
            }

            return $use(new TokenContext($workspace, (string) $token->id, $token->name, $token->abilities));
        };
        $connection = DB::connection();
        if (! $write && $connection instanceof WorkspaceSqliteConnection) {
            return $connection->readTransaction($execute);
        }

        return $connection->transaction($execute);
    }
}
