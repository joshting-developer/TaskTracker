<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Domain\DomainError;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\Integrations\IssueChangesRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\IssueChangeResource;
use App\Services\Integrations\IssueChangeService;
use Illuminate\Http\Request;

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

    /**
     * TT-250：卡片刪除墓碑分頁，limit預設50、上限100，after為上一頁nextAfter。
     *
     * @param  Request  $request  純Bearer與limit／after查詢參數
     * @param  string  $project  路徑Project公開ID
     * @return ApiResponse 墓碑摘要與下一頁游標
     *
     * @throws DomainError 參數無效、Token無效或無讀取權限
     */
    public function deletions(Request $request, string $project): ApiResponse
    {
        $limit = filter_var($request->query('limit', 50), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $after = $request->query('after');
        $after = $after === null ? null : filter_var($after, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false || $after === false) {
            DomainError::fail('INVALID_INPUT', '請確認查詢範圍');
        }

        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => $this->changes->deletions($token->workspace, $project, $after, $limit)));
    }
}
