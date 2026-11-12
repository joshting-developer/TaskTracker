<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectTimeQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\TimeEntryResource;
use App\Http\WorkspaceTransaction;
use App\Services\ProjectTimeReadService;

/** 專案工時月曆摘要與當日明細HTTP入口。 */
class ProjectTimeController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceTransaction  $workspace  此Controller的穩定協作者
     * @param  ProjectTimeReadService  $time  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceTransaction $workspace, private ProjectTimeReadService $time) {}

    /**
     * 整月摘要，不回傳原始工時列。
     *
     * @param  ProjectTimeQueryRequest  $request  已驗證月份與成員
     * @param  string  $project  專案公開ID
     * @return ApiResponse 月曆摘要
     */
    public function calendar(ProjectTimeQueryRequest $request, string $project): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->time->calendar($context, $project, $request->calendar())));
    }

    /**
     * 當日有效工時分頁。
     *
     * @param  ProjectTimeQueryRequest  $request  已驗證日期、成員與分頁
     * @param  string  $project  專案公開ID
     * @return ApiResponse 當日明細分頁
     */
    public function day(ProjectTimeQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->time->day($context, $project, $request->day())]);

        return new ApiResponse(PageResource::wrap($data['page'], TimeEntryResource::collection($data['page']->items())->resolve()));
    }
}
