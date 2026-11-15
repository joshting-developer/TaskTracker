<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminStatsRequest;
use App\Http\Resources\ApiResponse;
use App\Services\AdminStatisticsService;

/** 管理彙總與工作區列表分開載入, 不提供修改或業務內容入口 */
class AdminStatisticsController extends Controller
{
    /** 注入統計用例, 資料只經Service／Repository
     *
     * @param  AdminStatisticsService  $statistics  管理彙總用例
     */
    public function __construct(private AdminStatisticsService $statistics) {}

    /** 讀取固定期間彙總及每日趨勢
     *
     * @param  AdminStatsRequest  $request  固定期間及Session
     * @return ApiResponse 最多30日彙總
     */
    public function index(AdminStatsRequest $request): ApiResponse
    {
        return new ApiResponse($this->statistics->summary($request->session()->get('platform_admin'), $request->toDto()));
    }

    /** 依游標讀取必要工作區識別
     *
     * @param  AdminStatsRequest  $request  有界游標及Session
     * @return ApiResponse 20筆工作區必要識別
     */
    public function workspaces(AdminStatsRequest $request): ApiResponse
    {
        return new ApiResponse($this->statistics->workspaces($request->session()->get('platform_admin'), $request->toDto()));
    }
}
