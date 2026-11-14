<?php

namespace App\Http\Controllers;

use App\Data\WorkspaceContext;
use App\Http\Requests\Writes\TodoNotificationsReadRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceTransaction;
use App\Services\TodoNotificationService;

/** 本人通知的批次已讀入口, 不提供租戶或收件人參數 */
class TodoNotificationsController extends Controller
{
    /** 注入交易與通知用例
     * @param  WorkspaceTransaction  $workspace  Session與租戶交易邊界
     * @param  TodoNotificationService  $notifications  當次授權的通知寫入
     */
    public function __construct(private WorkspaceTransaction $workspace, private TodoNotificationService $notifications) {}

    /** 一次處理目前本人可讀通知
     * @param  TodoNotificationsReadRequest  $request  JSON操作識別碼
     * @return ApiResponse 固定寫入結果
     */
    public function readAll(TodoNotificationsReadRequest $request): ApiResponse
    {
        return new ApiResponse($this->workspace->write($request, fn (WorkspaceContext $context) => $this->notifications->readAll($context, $request->payload())));
    }
}
