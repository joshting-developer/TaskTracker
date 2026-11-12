<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\Integrations\IssueChangesRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\IssueChangeResource;
use App\Services\Integrations\IssueChangeService;

/** 公開PAT依單一領域分責查詢，不解析無關用例服務。 */
class PublicIssueChangesController extends Controller
{
    /**
     * 注入公開Bearer交易及資格邊界。
     *
     * @param  BearerTokenSession  $tokens  每次重驗資格的邊界
     * @param  IssueChangeService  $changes  當次HTTP用例的穩定協作者
     */
    public function __construct(private BearerTokenSession $tokens, private IssueChangeService $changes) {}

    /**
     * 以本輪固定水位回傳目前可讀Project的有界增量摘要。
     *
     * @param  IssueChangesRequest  $request  有界增量／接續條件
     * @param  string  $project  Project公開ID
     * @return ApiResponse 增量摘要、接續cursor及本輪固定水位
     */
    public function changes(IssueChangesRequest $request, string $project): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project) {
            $result = $this->changes->read($token->workspace, $project, $request->toChangesDto(), $token->tokenId);
            $result['items'] = array_map(fn ($item) => [...$item, 'lastWrite' => $result['lastWrites'][$item['id']] ?? null], IssueChangeResource::collection($result['items'])->resolve());
            unset($result['lastWrites']);

            return $result;
        }));
    }
}
