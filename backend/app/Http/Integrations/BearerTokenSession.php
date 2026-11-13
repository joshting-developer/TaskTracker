<?php

namespace App\Http\Integrations;

use App\Data\TokenContext;
use App\Database\WorkspaceSqliteConnection;
use App\Database\WriteLocks;
use App\Domain\DomainError;
use App\Factories\ProjectAccessFactory;
use App\Factories\RecordStoreFactory;
use App\Factories\WorkspaceContextFactory;
use App\Models\User;
use App\Services\HttpOperationLogs;
use App\Services\Integrations\IntegrationAudits;
use App\Services\Integrations\TokenIdentityService;
use App\Services\RequestReplay;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

/** 純Bearer交易及來源邊界；每次重新驗證，不使用Auth guard已快取的操作者。 */
class BearerTokenSession
{
    /**
     * 注入現行鎖／資格／Project授權與來源紀錄，不複製業務權限映射。
     *
     * @param  WorkspaceContextFactory  $contexts  重驗帳號與工作區資格
     * @param  WriteLocks  $locks  User→Workspace寫入鎖
     * @param  ProjectAccessFactory  $projects  依Context建立Project權限檢查
     * @param  IntegrationAudits  $audits  整合來源紀錄
     * @param  RecordStoreFactory  $stores  依工作區建立資料入口
     * @param  RequestReplay  $replay  防重複提交與原結果重播
     * @param  TokenIdentityService  $identities  無快取的Token及帳號資料用例
     */
    public function __construct(private WorkspaceContextFactory $contexts, private WriteLocks $locks, private ProjectAccessFactory $projects, private IntegrationAudits $audits, private RecordStoreFactory $stores, private RequestReplay $replay, private TokenIdentityService $identities) {}

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
     * 上傳保留階段的鎖後授權, 完成IO後才由writeOnce記成功receipt與來源
     *
     * @template T
     *
     * @param  Request  $request  純Bearer請求
     * @param  Closure(TokenContext):T  $use  保留配額／metadata, 不執行外部IO
     * @param  string  $projectId  目標Project公開ID
     * @return T 已保留的上傳資料
     */
    public function stageUpload(Request $request, Closure $use, string $projectId): mixed
    {
        return $this->run($request, true, $use, $projectId, 'issue.write');
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
     * 授權後才讀原結果；只有首次真正寫入同交易追加來源，重播不再發通知／audit。
     *
     * @param  Request  $request  純Bearer HTTP
     * @param  string  $operation  伺服器固定且與Session分離的操作名
     * @param  array  $input  已驗allowlist及路徑資源；用於requestId簽章
     * @param  Closure(TokenContext):void  $authorize  資源條件重驗，必須在receipt前執行
     * @param  Closure(TokenContext):array  $use  共用業務用例，回傳有界結果
     * @param  string|null  $projectId  Project公開ID（null時不檢查Project, 例如工作空間層級的小待辦）
     * @param  string  $permission  目標Project須具備的有效permission；刪卡為issue.delete
     * @return array 首次結果或相同請求原結果
     */
    public function writeOnce(Request $request, string $operation, array $input, Closure $authorize, Closure $use, ?string $projectId, string $permission = 'issue.write'): array
    {
        $reference = $this->clientReference($request);

        return $this->run($request, true, function (TokenContext $token) use ($operation, $input, $authorize, $use, $reference) {
            $authorize($token);

            return $this->replay->execute($this->stores->forContext($token->workspace), 'requests', $token->workspace->userId, $operation, $input, function () use ($token, $operation, $use, $reference) {
                $result = $use($token);
                // 批次逐項記錄成功寫入；重播不會進到這裡，所以補送不重複來源。
                $writes = isset($result['items']) ? array_filter($result['items'], fn ($item) => in_array($item['status'], ['created', 'updated'], true)) : [$result];
                foreach ($writes as $write) {
                    $this->audits->append($token->workspace, $operation, $write['id'] ?? null, $token, $write['revision'] ?? null, $reference);
                }

                return $result;
            });
        }, $projectId, $permission);
    }

    /**
     * TT-176：選填X-Client-Reference（外部run等參照），只進來源稽核，不進卡片或receipt簽章。
     *
     * @param  Request  $request  Bearer請求
     * @return string|null 已驗參照
     */
    private function clientReference(Request $request): ?string
    {
        $value = $request->header('X-Client-Reference');
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || preg_match('/^[A-Za-z0-9._:\/@#-]{1,120}$/', $value) !== 1) {
            DomainError::fail('INVALID_INPUT', 'X-Client-Reference只接受英數與._:/@#-，最多120字');
        }

        return $value;
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
        $hint = $write && $plain ? $this->identities->token($plain) : null;
        if (! $plain || ($write && (! $hint || $hint->tokenable_type !== User::class))) {
            DomainError::fail('UNAUTHENTICATED', 'Token無效、已過期或已撤銷');
        }
        $workspaceId = $hint ? $this->identities->workspaceId($hint->workspace_id) : null;
        $execute = function () use ($plain, $hint, $workspaceId, $write, $use, $projectId, $permission) {
            if ($write) {
                $this->locks->account($hint->tokenable_id);
                $this->locks->workspace($workspaceId);
            }
            $token = $this->identities->token($plain);
            if (! $token || $token->tokenable_type !== User::class || $token->revoked_at !== null || ! $token->expires_at || $token->expires_at->isPast()
                || ($hint && ($token->tokenable_id !== $hint->tokenable_id || $token->workspace_id !== $hint->workspace_id))) {
                DomainError::fail('UNAUTHENTICATED', 'Token無效、已過期或已撤銷');
            }
            $currentWorkspaceId = $write ? $workspaceId : $this->identities->workspaceId($token->workspace_id);
            $workspace = $this->contexts->verify($this->identities->user($token->tokenable_id), $currentWorkspaceId);
            // TT-260：Token與資格重驗成功後放入請求範圍Context；FormRequest是複本，attributes不會回到原請求。
            Context::addHidden(HttpOperationLogs::TOKEN_ACTOR, ['workspaceId' => $workspace->workspaceId, 'userId' => $workspace->userId, 'tokenId' => (string) $token->id]);
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
