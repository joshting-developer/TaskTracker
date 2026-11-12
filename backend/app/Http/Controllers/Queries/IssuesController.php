<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Database\DatabaseTimes;
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
use App\Http\WorkspaceTransaction;
use App\Services\IssueReadService;
use Illuminate\Http\Request;

/** 任務列表、詳細屬性及活動各自有界，不回傳整個工作空間。 */
class IssuesController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceTransaction  $workspace  此Controller的穩定協作者
     * @param  IssueReadService  $issues  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceTransaction $workspace, private IssueReadService $issues) {}

    /**
     * 取得當次已授權且有界的列表，不附帶其他頁面的資料。
     *
     * @param  IssueQueryRequest  $request  已驗證HTTP查詢
     * @param  string  $project  Project公開ID
     * @return ApiResponse 此資源的公開投影
     */
    public function index(IssueQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->issues->index($context, $project, $request->toDto())]);

        return new ApiResponse(PageResource::wrap($data['page'], IssueListResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 搜尋單Project最小候選，避免編輯器載入全部卡片。
     *
     * @param  IssueOptionQueryRequest  $request  已驗證候選條件
     * @param  string  $project  Project公開ID
     * @return ApiResponse 候選分頁
     */
    public function options(IssueOptionQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->issues->options($context, $project, $request->toDto())]);
        $rows = array_map(fn ($issue) => (new IssueOptionResource($issue, $project))->resolve(), $data['page']->items());

        return new ApiResponse(PageResource::wrap($data['page'], $rows));
    }

    /**
     * 只取得指定資源的資料，保留找不到／無權限語意。
     *
     * @param  Request  $request  已驗證HTTP查詢
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @return ApiResponse 此資源的公開投影
     */
    public function show(Request $request, string $project, string $issue): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['issue' => $this->issues->show($context, $project, $issue)]);

        return new ApiResponse((new IssueDetailResource($data['issue']))->resolve());
    }

    /**
     * 舊版深連結只回傳卡片所屬Project，不附任務內容。
     *
     * @param  Request  $request  HTTP請求
     * @param  string  $issue  Issue公開ID
     * @return ApiResponse 卡片位置
     */
    public function locate(Request $request, string $issue): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->issues->locate($context, $issue)));
    }

    /**
     * 只查這個任務的留言分頁，不載入全部活動。
     *
     * @param  PageQueryRequest  $request  已驗證HTTP查詢
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @return ApiResponse 此資源的公開投影
     */
    public function comments(PageQueryRequest $request, string $project, string $issue): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->issues->comments($context, $project, $issue, $request->pagination())]);
        $rows = array_map(fn ($row) => ['id' => $row->id, 'content' => $row->content, 'author' => $row->author, 'createdAt' => DatabaseTimes::public($row->created_at)], $data['page']->items());

        return new ApiResponse(PageResource::wrap($data['page'], $rows));
    }

    /**
     * 只查這個任務的異動分頁，維持穩定排序。
     *
     * @param  PageQueryRequest  $request  已驗證HTTP查詢
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @return ApiResponse 此資源的公開投影
     */
    public function history(PageQueryRequest $request, string $project, string $issue): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->issues->history($context, $project, $issue, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($row) => json_decode($row->event_data, true, flags: JSON_THROW_ON_ERROR), $data['page']->items())));
    }

    /**
     * 關聯tab按需取得有界子卡摘要。
     *
     * @param  PageQueryRequest  $request  分頁條件
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @return ApiResponse 子卡摘要分頁
     */
    public function children(PageQueryRequest $request, string $project, string $issue): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->issues->children($context, $project, $issue, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], IssueSummaryResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 單卡工時tab只讀本次分頁，不夾帶其他月份或任務資料。
     *
     * @param  PageQueryRequest  $request  分頁條件
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @return ApiResponse 有效工時分頁
     */
    public function time(PageQueryRequest $request, string $project, string $issue): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->issues->time($context, $project, $issue, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], TimeEntryResource::collection($data['page']->items())->resolve()));
    }
}
