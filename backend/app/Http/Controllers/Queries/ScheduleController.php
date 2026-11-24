<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ScheduleIssueResource;
use App\Http\WorkspaceTransaction;
use App\Services\ScheduleService;

/** Session時程視圖入口, 尚不提供PAT。 */
class ScheduleController extends Controller
{
    /**
     * 注入可信工作區邊界與時程用例, 建構時不查資料。
     *
     * @param  WorkspaceTransaction  $workspace  工作區交易邊界
     * @param  ScheduleService  $schedule  時程用例
     */
    public function __construct(private WorkspaceTransaction $workspace, private ScheduleService $schedule) {}

    /**
     * 讀取與區間重疊的當頁卡片及未排程數。
     *
     * @param  ScheduleQueryRequest  $request  已驗證區間、承辦者與分頁
     * @param  string  $project  Project公開ID
     * @return ApiResponse 時程分頁
     */
    public function index(ScheduleQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => $this->schedule->index($context, $project, $request->schedule()));

        return new ApiResponse([...PageResource::wrap($data['page'], ScheduleIssueResource::collection($data['page']->items())->resolve()), 'unscheduled' => $data['unscheduled']]);
    }
}
