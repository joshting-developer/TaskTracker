<?php

namespace App\Http\Controllers\Queries;

use App\Database\DatabaseTimes;
use App\Data\WorkspaceContext;
use App\Http\WorkspaceTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueOptionQueryRequest;
use App\Http\Requests\IssueQueryRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\IssueDetailResource;
use App\Http\Resources\Queries\IssueListResource;
use App\Http\Resources\Queries\IssueOptionResource;
use App\Http\Resources\Queries\IssueSummaryResource;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\TimeEntryResource;
use App\Services\IssueReadService;
use Illuminate\Http\Request;

/** 任務列表、詳細屬性及活動各自有界，不回傳整個工作空間。 */
class IssuesController extends Controller
{
    /**
     * 取得當次已授權且有界的列表，不附帶其他頁面的資料。
     *
     * @param  IssueQueryRequest  $request  已驗證HTTP查詢
     * @param  string  $project  Project公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  IssueReadService  $issues  任務查詢用例
     * @return ApiResponse 此資源的公開投影
     */
    public function index(IssueQueryRequest $request, string $project, WorkspaceTransaction $workspace, IssueReadService $issues): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $issues->index($context, $project, $request->toDto())]);

        return new ApiResponse(PageResource::wrap($data['page'], IssueListResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 搜尋單Project最小候選，避免編輯器載入全部卡片。
     *
     * @param  IssueOptionQueryRequest  $request  已驗證候選條件
     * @param  string  $project  Project公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  IssueReadService  $issues  任務查詢用例
     * @return ApiResponse 候選分頁
     */
    public function options(IssueOptionQueryRequest $request, string $project, WorkspaceTransaction $workspace, IssueReadService $issues): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $issues->options($context, $project, $request->toDto())]);
        $rows = array_map(fn ($issue) => (new IssueOptionResource($issue, $project))->resolve(), $data['page']->items());

        return new ApiResponse(PageResource::wrap($data['page'], $rows));
    }

    /**
     * 只取得指定資源的資料，保留找不到／無權限語意。
     *
     * @param  Request  $request  已驗證HTTP查詢
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  IssueReadService  $issues  任務查詢用例
     * @return ApiResponse 此資源的公開投影
     */
    public function show(Request $request, string $project, string $issue, WorkspaceTransaction $workspace, IssueReadService $issues): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['issue' => $issues->show($context, $project, $issue)]);

        return new ApiResponse((new IssueDetailResource($data['issue']))->resolve());
    }

    /**
     * 舊版深連結只回傳卡片所屬Project，不附任務內容。
     *
     * @param  Request  $request  HTTP請求
     * @param  string  $issue  Issue公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  IssueReadService  $issues  任務查詢用例
     * @return ApiResponse 卡片位置
     */
    public function locate(Request $request, string $issue, WorkspaceTransaction $workspace, IssueReadService $issues): ApiResponse
    {
        return new ApiResponse($workspace->read($request, fn (WorkspaceContext $context) => $issues->locate($context, $issue)));
    }

    /**
     * 只查這個任務的留言分頁，不載入全部活動。
     *
     * @param  PageQueryRequest  $request  已驗證HTTP查詢
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  IssueReadService  $issues  任務查詢用例
     * @return ApiResponse 此資源的公開投影
     */
    public function comments(PageQueryRequest $request, string $project, string $issue, WorkspaceTransaction $workspace, IssueReadService $issues): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $issues->comments($context, $project, $issue, $request->pagination())]);
        $rows = array_map(fn ($row) => ['id' => $row->id, 'content' => $row->content, 'author' => $row->author, 'createdAt' => DatabaseTimes::public($row->created_at)], $data['page']->items());

        return new ApiResponse(PageResource::wrap($data['page'], $rows));
    }

    /**
     * 只查這個任務的異動分頁，維持穩定排序。
     *
     * @param  PageQueryRequest  $request  已驗證HTTP查詢
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  IssueReadService  $issues  任務查詢用例
     * @return ApiResponse 此資源的公開投影
     */
    public function history(PageQueryRequest $request, string $project, string $issue, WorkspaceTransaction $workspace, IssueReadService $issues): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $issues->history($context, $project, $issue, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($row) => json_decode($row->event_data, true, flags: JSON_THROW_ON_ERROR), $data['page']->items())));
    }

    /**
     * 關聯tab按需取得有界子卡摘要。
     *
     * @param  PageQueryRequest  $request  分頁條件
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  IssueReadService  $issues  任務查詢用例
     * @return ApiResponse 子卡摘要分頁
     */
    public function children(PageQueryRequest $request, string $project, string $issue, WorkspaceTransaction $workspace, IssueReadService $issues): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $issues->children($context, $project, $issue, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], IssueSummaryResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 單卡工時tab只讀本次分頁，不夾帶其他月份或任務資料。
     *
     * @param  PageQueryRequest  $request  分頁條件
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  IssueReadService  $issues  任務查詢用例
     * @return ApiResponse 有效工時分頁
     */
    public function time(PageQueryRequest $request, string $project, string $issue, WorkspaceTransaction $workspace, IssueReadService $issues): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $issues->time($context, $project, $issue, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], TimeEntryResource::collection($data['page']->items())->resolve()));
    }
}
