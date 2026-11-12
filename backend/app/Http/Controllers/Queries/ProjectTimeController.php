<?php

namespace App\Http\Controllers\Queries;

use App\Http\ApiExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectTimeQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\TimeEntryResource;
use App\Services\ProjectTimeReadService;

/** 專案工時月曆摘要與當日明細HTTP入口。 */
class ProjectTimeController extends Controller
{
    /**
     * 整月摘要，不回傳原始工時列。
     *
     * @param  ProjectTimeQueryRequest  $request  已驗證月份與成員
     * @param  string  $project  專案公開ID
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  ProjectTimeReadService  $time  工時查詢用例
     * @return ApiResponse 月曆摘要
     */
    public function calendar(ProjectTimeQueryRequest $request, string $project, ApiExecution $execution, ProjectTimeReadService $time): ApiResponse
    {
        return new ApiResponse($execution->query($request, fn ($actor) => $time->calendar($actor, $project, $request->calendar())));
    }

    /**
     * 當日有效工時分頁。
     *
     * @param  ProjectTimeQueryRequest  $request  已驗證日期、成員與分頁
     * @param  string  $project  專案公開ID
     * @param  ApiExecution  $execution  可信資格與交易邊界
     * @param  ProjectTimeReadService  $time  工時查詢用例
     * @return ApiResponse 當日明細分頁
     */
    public function day(ProjectTimeQueryRequest $request, string $project, ApiExecution $execution, ProjectTimeReadService $time): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $time->day($actor, $project, $request->day())]);

        return new ApiResponse(PageResource::wrap($data['page'], TimeEntryResource::collection($data['page']->items())->resolve()));
    }
}
