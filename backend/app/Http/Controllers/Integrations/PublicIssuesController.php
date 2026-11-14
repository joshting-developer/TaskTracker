<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Domain\DomainError;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\Integrations\PublicIssueWriteRequest;
use App\Http\Resources\ApiResponse;
use App\Services\IssueReadService;
use App\Services\WorkspaceService;

/** 公開Issue寫入薄適配器，使用既有業務、授權與有界冪等回應。 */
class PublicIssuesController extends Controller
{
    /**
     * 注入當次Bearer交易與共用應用服務；不操作DB或Session。
     *
     * @param  BearerTokenSession  $tokens  Bearer Token交易與資格邊界
     * @param  WorkspaceService  $workspaces  沿用的卡片寫入RPC用例
     * @param  IssueReadService  $issues  卡片讀取用例
     */
    public function __construct(private BearerTokenSession $tokens, private WorkspaceService $workspaces, private IssueReadService $issues) {}

    /**
     * 建卡／更新／留言／刪除依伺服器命名路由決定用例，不能由payload任意選操作。
     *
     * @param  PublicIssueWriteRequest  $request  已驗證有限JSON
     * @param  string  $project  路徑Project公開ID
     * @param  string|null  $issue  路徑Issue公開ID；建立時無
     * @return ApiResponse 有界id／identifier／projectId／revision，不帶歷史／留言
     */
    public function execute(PublicIssueWriteRequest $request, string $project, ?string $issue = null): ApiResponse
    {
        $operation = match ($request->route()->getName()) {
            'public.issues.create' => 'createIssue',
            'public.issues.update' => 'updateIssue',
            'public.issues.comment' => 'addComment',
            'public.issues.delete' => 'deleteIssue',
        };
        $input = [...$request->validated(), 'projectId' => $project, ...($issue ? ['id' => $issue] : [])];

        return new ApiResponse($this->tokens->writeOnce($request, 'public.v1.'.$operation, $input,
            function (TokenContext $token) use ($project, $issue, $operation, $input) {
                if (! $issue) {
                    $this->workspaces->authorizePublicIssueCreate($token->workspace->legacyIdentity(), $input);

                    return;
                }
                try {
                    $location = $this->issues->locate($token->workspace, $issue);
                } catch (DomainError $missing) {
                    // 刪除後同requestId重播時卡片已不存在：Project層級issue.delete已在交易內重驗，交由receipt回原結果；首次請求仍由Service回404。
                    if ($operation === 'deleteIssue') {
                        return;
                    }
                    throw $missing;
                }
                if ($location['projectId'] !== $project) {
                    DomainError::fail('NOT_FOUND', '找不到這個專案內的任務');
                }
            }, fn (TokenContext $token) => $this->workspaces->executePublicIssue($token->workspace->legacyIdentity(), $operation, $input), $project,
            $operation === 'deleteIssue' ? 'issue.delete' : 'issue.write'));
    }
}
