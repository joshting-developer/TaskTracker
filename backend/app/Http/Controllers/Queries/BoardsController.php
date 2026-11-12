<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\WorkspaceTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueQueryRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\BoardSummaryResource;
use App\Http\Resources\Queries\IssueSummaryResource;
use App\Http\Resources\Queries\PageResource;
use App\Services\BoardReadService;
use Illuminate\Http\Request;

/** 看板摘要、指定看板與卡片分頁各自讀取。 */
class BoardsController extends Controller
{
    /**
     * 取得同Project的看板摘要分頁及Default。
     *
     * @param  PageQueryRequest  $request  已驗證分頁
     * @param  string  $project  Project公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  BoardReadService  $boards  看板用例
     * @return ApiResponse 摘要分頁
     */
    public function index(PageQueryRequest $request, string $project, WorkspaceTransaction $workspace, BoardReadService $boards): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => $boards->index($context, $project, $request->pagination()));
        $items = array_map(fn ($b) => (new BoardSummaryResource($b, $project))->resolve(), $data['page']->items());

        return new ApiResponse([...PageResource::wrap($data['page'], $items), 'defaultBoard' => $data['defaultBoard']]);
    }

    /**
     * 讀取指定看板的屬性。
     *
     * @param  Request  $request  HTTP租戶標頭
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  BoardReadService  $boards  看板用例
     * @return ApiResponse 看板屬性
     */
    public function show(Request $request, string $project, string $board, WorkspaceTransaction $workspace, BoardReadService $boards): ApiResponse
    {
        return new ApiResponse($workspace->read($request, fn (WorkspaceContext $context) => $boards->show($context, $project, $board)));
    }

    /**
     * 輕量變更檢查，版本不變時前端不必重讀Project、看板與卡片分頁。
     *
     * @param  Request  $request  HTTP租戶標頭
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID或default
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  BoardReadService  $boards  看板用例
     * @return ApiResponse 不透明版本
     */
    public function version(Request $request, string $project, string $board, WorkspaceTransaction $workspace, BoardReadService $boards): ApiResponse
    {
        return new ApiResponse($workspace->read($request, fn (WorkspaceContext $context) => $boards->version($context, $project, $board)));
    }

    /**
     * 只讀此看板的卡片摘要分頁。
     *
     * @param  IssueQueryRequest  $request  已驗證篩選
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  BoardReadService  $boards  看板用例
     * @return ApiResponse 卡片分頁
     */
    public function issues(IssueQueryRequest $request, string $project, string $board, WorkspaceTransaction $workspace, BoardReadService $boards): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $boards->issues($context, $project, $board, $request->toDto())]);

        return new ApiResponse(PageResource::wrap($data['page'], IssueSummaryResource::collection($data['page']->items())->resolve()));
    }
}
