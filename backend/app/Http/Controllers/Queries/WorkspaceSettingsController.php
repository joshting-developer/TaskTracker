<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\WorkspaceTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\WorkspaceMemberResource;
use App\Services\WorkspaceSettingsReadService;
use Illuminate\Http\Request;

/** 工作區設定、管理目錄與角色各自獨立的HTTP入口。 */
class WorkspaceSettingsController extends Controller
{
    /**
     * 一般設定不讀成員目錄。
     *
     * @param  Request  $request  HTTP請求
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  WorkspaceSettingsReadService  $settings  設定查詢用例
     * @return ApiResponse 一般設定
     */
    public function show(Request $request, WorkspaceTransaction $workspace, WorkspaceSettingsReadService $settings): ApiResponse
    {
        return new ApiResponse($workspace->read($request, fn (WorkspaceContext $context) => $settings->show($context)));
    }

    /**
     * 按需回傳有界有效成員目錄。
     *
     * @param  PageQueryRequest  $request  分頁及搜尋條件
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  WorkspaceSettingsReadService  $settings  設定查詢用例
     * @return ApiResponse 當頁成員
     */
    public function members(PageQueryRequest $request, WorkspaceTransaction $workspace, WorkspaceSettingsReadService $settings): ApiResponse
    {
        $data = $workspace->read($request, fn (WorkspaceContext $context) => ['page' => $settings->members($context, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], WorkspaceMemberResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 只輸出目前能授予的固定角色選項。
     *
     * @param  Request  $request  HTTP請求
     * @param  WorkspaceTransaction  $workspace  交易內重驗身分與租戶的唯讀邊界
     * @param  WorkspaceSettingsReadService  $settings  設定查詢用例
     * @return ApiResponse 固定角色選項
     */
    public function roles(Request $request, WorkspaceTransaction $workspace, WorkspaceSettingsReadService $settings): ApiResponse
    {
        return new ApiResponse($workspace->read($request, fn (WorkspaceContext $context) => $settings->roles($context)));
    }
}
