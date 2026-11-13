<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Database\DatabaseTimes;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\IssueQueryRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\IssueDetailResource;
use App\Http\Resources\Queries\IssueListPageResource;
use App\Http\Resources\Queries\IssueListResource;
use App\Http\Resources\Queries\PageResource;
use App\Services\IssueReadService;
use Illuminate\Http\Request;

/** 公開PAT依單一領域分責查詢，不解析無關用例服務。 */
class PublicQueriesController extends Controller
{
    /**
     * 注入公開Bearer交易及資格邊界。
     *
     * @param  BearerTokenSession  $tokens  每次重驗資格的邊界
     * @param  IssueReadService  $issues  當次HTTP用例的穩定協作者
     */
    public function __construct(private BearerTokenSession $tokens, private IssueReadService $issues) {}

    /**
     * 共用SQL篩選及穩定排序，identifier精準定位仍回摘要分頁。
     *
     * @param  IssueQueryRequest  $request  有界任務查詢
     * @param  string  $project  Project公開ID
     * @return ApiResponse 不含描述／活動的任務摘要
     */
    public function issues(IssueQueryRequest $request, string $project): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project) {
            $page = $this->issues->index($token->workspace, $project, $request->toDto());

            return IssueListPageResource::wrap($page, IssueListResource::collection($page->items())->resolve());
        }));
    }

    /**
     * 單卡取得可編輯屬性及最新revision，不附留言／異動全集。
     *
     * @param  Request  $request  純Bearer
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID，卡號先用identifier篩選取得
     * @return ApiResponse 完整單卡屬性與有限關聯參照
     */
    public function issue(Request $request, string $project, string $issue): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => (new IssueDetailResource($this->issues->show($token->workspace, $project, $issue)))->resolve()));
    }

    /**
     * 按需取得指定卡片留言，不同時讀取其他活動或卡片。
     *
     * @param  PageQueryRequest  $request  分頁條件
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @return ApiResponse 單卡留言分頁
     */
    public function comments(PageQueryRequest $request, string $project, string $issue): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $issue) {
            $page = $this->issues->comments($token->workspace, $project, $issue, $request->pagination());

            return PageResource::wrap($page, array_map(fn ($row) => ['id' => $row->id, 'content' => $row->content, 'author' => $row->author, 'createdAt' => DatabaseTimes::public($row->created_at)], $page->items()));
        }));
    }
}
