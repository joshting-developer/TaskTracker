<?php

namespace App\Http\Integrations;

use App\Domain\DomainError;
use App\Factories\WorkspaceContextFactory;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** 純Bearer的HTTP可信邊界，不讀人類瀏覽器目前Workspace Session。 */
class BearerTokenSession
{
    /** 注入既有資格重驗Factory，不複製permission／角色邏輯。 */
    public function __construct(private WorkspaceContextFactory $contexts) {}

    /**
     * 驗證Sanctum憑證、read能力與固定Workspace當下資格，回傳最小身分投影。
     *
     * @param  Request  $request  僅從Authorization header取Bearer
     * @return array{userId:string,workspaceId:string,abilities:array} 不含Token原文、雜湊或其他成員
     */
    public function identity(Request $request): array
    {
        return DB::transaction(function () use ($request) {
            $user = Auth::guard('sanctum')->user();
            if (! $request->bearerToken() || ! $user || ! $user->currentAccessToken()) {
                DomainError::fail('UNAUTHENTICATED', 'Token無效、已過期或已撤銷');
            }
            $token = $user->currentAccessToken();
            if (! $token->can('read')) {
                DomainError::fail('FORBIDDEN', 'Token未授權讀取');
            }
            $workspace = Workspace::find($token->workspace_id, ['public_id']);
            if (! $workspace) {
                DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');
            }
            $context = $this->contexts->verify($user->fresh(), $workspace->public_id);

            return ['userId' => $context->userId, 'workspaceId' => $context->workspaceId, 'abilities' => $token->abilities];
        });
    }
}
