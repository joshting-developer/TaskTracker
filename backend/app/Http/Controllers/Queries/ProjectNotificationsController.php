<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\WorkspaceTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ProjectNotificationResource;
use App\Services\ProjectNotificationReadService;
use Illuminate\Http\Request;

/** 專案既有通知預覽之摘要及正文HTTP入口。 */
class ProjectNotificationsController extends Controller
{
    /**
     * 只回傳當頁通知，不附全部卡片或正文。
     *
     * @param  PageQueryRequest  $request  有界分頁請求
     * @param  string  $project  專案公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  ProjectNotificationReadService  $notifications  通知查詢用例
     * @return ApiResponse 通知摘要分頁
     */
    public function index(PageQueryRequest $request, string $project, WorkspaceTransaction $workspace, ProjectNotificationReadService $notifications): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $notifications->index($context, $project, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], ProjectNotificationResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 按需讀取單筆通知正文。
     *
     * @param  Request  $request  HTTP請求
     * @param  string  $project  專案公開ID
     * @param  string  $notification  通知公開ID
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  ProjectNotificationReadService  $notifications  通知查詢用例
     * @return ApiResponse 單筆預覽
     */
    public function show(Request $request, string $project, string $notification, WorkspaceTransaction $workspace, ProjectNotificationReadService $notifications): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['notification' => $notifications->show($context, $project, $notification)]);

        return new ApiResponse((new ProjectNotificationResource($data['notification']))->resolve());
    }
}
