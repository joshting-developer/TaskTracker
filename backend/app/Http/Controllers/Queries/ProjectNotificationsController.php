<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ProjectNotificationResource;
use App\Http\WorkspaceTransaction;
use App\Services\ProjectNotificationReadService;
use Illuminate\Http\Request;

/** 專案既有通知預覽之摘要及正文HTTP入口。 */
class ProjectNotificationsController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceTransaction  $workspace  此Controller的穩定協作者
     * @param  ProjectNotificationReadService  $notifications  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceTransaction $workspace, private ProjectNotificationReadService $notifications) {}

    /**
     * 只回傳當頁通知，不附全部卡片或正文。
     *
     * @param  PageQueryRequest  $request  有界分頁請求
     * @param  string  $project  專案公開ID
     * @return ApiResponse 通知摘要分頁
     */
    public function index(PageQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->notifications->index($context, $project, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], ProjectNotificationResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 按需讀取單筆通知正文。
     *
     * @param  Request  $request  HTTP請求
     * @param  string  $project  專案公開ID
     * @param  string  $notification  通知公開ID
     * @return ApiResponse 單筆預覽
     */
    public function show(Request $request, string $project, string $notification): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['notification' => $this->notifications->show($context, $project, $notification)]);

        return new ApiResponse((new ProjectNotificationResource($data['notification']))->resolve());
    }
}
