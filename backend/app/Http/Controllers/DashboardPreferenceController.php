<?php

namespace App\Http\Controllers;

use App\Data\WorkspaceContext;
use App\Http\Requests\DashboardPreferenceRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceTransaction;
use App\Services\DashboardPreferenceService;
use Illuminate\Http\Request;

/** TT-186：首頁小工具偏好的Session端點, 不提供PAT */
class DashboardPreferenceController extends Controller
{
    /** 注入交易邊界與偏好規則
     * @param  WorkspaceTransaction  $workspace  工作區交易邊界
     * @param  DashboardPreferenceService  $preferences  偏好規則
     */
    public function __construct(private WorkspaceTransaction $workspace, private DashboardPreferenceService $preferences) {}

    /** 讀取目前偏好
     * @param  Request  $request  HTTP請求
     * @return ApiResponse 偏好
     */
    public function show(Request $request): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->preferences->show($context)));
    }

    /** 整份覆寫偏好
     * @param  DashboardPreferenceRequest  $request  已驗證順序與版本
     * @return ApiResponse 保存後偏好
     */
    public function update(DashboardPreferenceRequest $request): ApiResponse
    {
        return new ApiResponse($this->workspace->write($request, fn (WorkspaceContext $context) => $this->preferences->update($context, $request->validated('widgets'), (int) $request->validated('expectedRevision'))));
    }
}
