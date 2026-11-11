<?php

namespace App\Http\Controllers\Queries;

use App\Database\DatabaseTimes;
use App\Http\ApiExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueQueryRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\IssueDetailResource;
use App\Http\Resources\Queries\IssueSummaryResource;
use App\Http\Resources\Queries\PageResource;
use App\Services\IssueReadService;
use Illuminate\Http\Request;

/** 任務列表、詳細屬性及活動各自有界，不回傳整個工作空間。 */
class IssuesController extends Controller
{
    /** 取得當次已授權且有界的列表，不附帶其他頁面的資料。 */
    public function index(IssueQueryRequest $request, string $project, ApiExecution $execution, IssueReadService $issues): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $issues->index($actor, $project, $request->toDto())]);

        return new ApiResponse(PageResource::wrap($data['page'], IssueSummaryResource::collection($data['page']->items())->resolve()));
    }

    /** 只取得指定資源的資料，保留找不到／無權限語意。 */
    public function show(Request $request, string $project, string $issue, ApiExecution $execution, IssueReadService $issues): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['issue' => $issues->show($actor, $project, $issue)]);

        return new ApiResponse((new IssueDetailResource($data['issue']))->resolve());
    }

    /** 只查這個任務的留言分頁，不載入全部活動。 */
    public function comments(PageQueryRequest $request, string $project, string $issue, ApiExecution $execution, IssueReadService $issues): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $issues->comments($actor, $project, $issue, $request->pagination())]);
        $rows = array_map(fn ($row) => ['id' => $row->id, 'content' => $row->content, 'author' => $row->author, 'createdAt' => DatabaseTimes::public($row->created_at)], $data['page']->items());

        return new ApiResponse(PageResource::wrap($data['page'], $rows));
    }

    /** 只查這個任務的異動分頁，維持穩定排序。 */
    public function history(PageQueryRequest $request, string $project, string $issue, ApiExecution $execution, IssueReadService $issues): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $issues->history($actor, $project, $issue, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($row) => json_decode($row->event_data, true, flags: JSON_THROW_ON_ERROR), $data['page']->items())));
    }
}
