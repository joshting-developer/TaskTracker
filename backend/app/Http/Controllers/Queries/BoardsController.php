<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\BoardPageQueryRequest;
use App\Http\Requests\IssueQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\BoardSummaryResource;
use App\Http\Resources\Queries\IssueSummaryResource;
use App\Http\Resources\Queries\PageResource;
use App\Http\WorkspaceTransaction;
use App\Services\BoardReadService;
use Illuminate\Http\Request;

/** 看板摘要、指定看板與卡片分頁各自讀取。 */
class BoardsController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceTransaction  $workspace  此Controller的穩定協作者
     * @param  BoardReadService  $boards  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceTransaction $workspace, private BoardReadService $boards) {}

    /**
     * 取得同Project的看板摘要分頁及Default。
     *
     * @param  PageQueryRequest  $request  已驗證分頁
     * @param  string  $project  Project公開ID
     * @return ApiResponse 摘要分頁
     */
    public function index(BoardPageQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => $this->boards->index($context, $project, $request->pagination(), $request->archived()));
        $items = array_map(fn ($b) => (new BoardSummaryResource($b, $project))->resolve(), $data['page']->items());

        return new ApiResponse([...PageResource::wrap($data['page'], $items), 'defaultBoard' => $data['defaultBoard']]);
    }

    /**
     * 讀取指定看板的屬性。
     *
     * @param  Request  $request  HTTP租戶標頭
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID
     * @return ApiResponse 看板屬性
     */
    public function show(Request $request, string $project, string $board): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->boards->show($context, $project, $board)));
    }

    /**
     * 輕量變更檢查，版本不變時前端不必重讀Project、看板與卡片分頁。
     *
     * @param  Request  $request  HTTP租戶標頭
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID或default
     * @return ApiResponse 不透明版本
     */
    public function version(Request $request, string $project, string $board): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->boards->version($context, $project, $board)));
    }

    /**
     * 只讀此看板的卡片摘要分頁。
     *
     * @param  IssueQueryRequest  $request  已驗證篩選
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID
     * @return ApiResponse 卡片分頁
     */
    public function issues(IssueQueryRequest $request, string $project, string $board): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->boards->issues($context, $project, $board, $request->toDto())]);

        return new ApiResponse(PageResource::wrap($data['page'], IssueSummaryResource::collection($data['page']->items())->resolve()));
    }
}
