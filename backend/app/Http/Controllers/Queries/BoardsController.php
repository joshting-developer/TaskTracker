<?php

namespace App\Http\Controllers\Queries;

use App\Http\ApiExecution;
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
     * @param  ApiExecution  $execution  HTTP資格與交易邊界
     * @param  BoardReadService  $boards  看板用例
     * @return ApiResponse 摘要分頁
     */
    public function index(PageQueryRequest $request, string $project, ApiExecution $execution, BoardReadService $boards): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => $boards->index($actor, $project, $request->pagination()));
        $items = array_map(fn ($b) => (new BoardSummaryResource($b, $project))->resolve(), $data['page']->items());

        return new ApiResponse([...PageResource::wrap($data['page'], $items), 'defaultBoard' => $data['defaultBoard']]);
    }

    /**
     * 讀取指定看板的屬性。
     *
     * @param  Request  $request  HTTP租戶標頭
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID
     * @param  ApiExecution  $execution  HTTP資格邊界
     * @param  BoardReadService  $boards  看板用例
     * @return ApiResponse 看板屬性
     */
    public function show(Request $request, string $project, string $board, ApiExecution $execution, BoardReadService $boards): ApiResponse
    {
        return new ApiResponse($execution->query($request, fn ($actor) => $boards->show($actor, $project, $board)));
    }

    /**
     * 只讀此看板的卡片摘要分頁。
     *
     * @param  IssueQueryRequest  $request  已驗證篩選
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID
     * @param  ApiExecution  $execution  HTTP資格邊界
     * @param  BoardReadService  $boards  看板用例
     * @return ApiResponse 卡片分頁
     */
    public function issues(IssueQueryRequest $request, string $project, string $board, ApiExecution $execution, BoardReadService $boards): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $boards->issues($actor, $project, $board, $request->toDto())]);

        return new ApiResponse(PageResource::wrap($data['page'], IssueSummaryResource::collection($data['page']->items())->resolve()));
    }
}
