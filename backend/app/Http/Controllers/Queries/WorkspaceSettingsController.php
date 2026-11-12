<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\WorkspaceMemberResource;
use App\Http\WorkspaceTransaction;
use App\Services\WorkspaceSettingsReadService;
use Illuminate\Http\Request;

/** 工作區設定、管理目錄與角色各自獨立的HTTP入口。 */
class WorkspaceSettingsController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceTransaction  $workspace  此Controller的穩定協作者
     * @param  WorkspaceSettingsReadService  $settings  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceTransaction $workspace, private WorkspaceSettingsReadService $settings) {}

    /**
     * 一般設定不讀成員目錄。
     *
     * @param  Request  $request  HTTP請求
     * @return ApiResponse 一般設定
     */
    public function show(Request $request): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->settings->show($context)));
    }

    /**
     * 按需回傳有界有效成員目錄。
     *
     * @param  PageQueryRequest  $request  分頁及搜尋條件
     * @return ApiResponse 當頁成員
     */
    public function members(PageQueryRequest $request): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->settings->members($context, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], WorkspaceMemberResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 只輸出目前能授予的固定角色選項。
     *
     * @param  Request  $request  HTTP請求
     * @return ApiResponse 固定角色選項
     */
    public function roles(Request $request): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->settings->roles($context)));
    }
}
