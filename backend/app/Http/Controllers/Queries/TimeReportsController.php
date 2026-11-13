<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Requests\TimeReportQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\WorkspaceTransaction;
use App\Services\WorkspaceTimeReport;

/** 工時分析的有界讀取入口 */
class TimeReportsController extends Controller
{
    /**
     * 注入可信交易邊界與讀取服務
     *
     * @param  WorkspaceTransaction  $workspace  Session資格重驗
     * @param  WorkspaceTimeReport  $reports  工時分析用例
     */
    public function __construct(private WorkspaceTransaction $workspace, private WorkspaceTimeReport $reports) {}

    /**
     * 回傳統計, 不附帶選項與任務明細
     *
     * @param  TimeReportQueryRequest  $request  驗證的篩選
     * @return ApiResponse 統計
     */
    public function index(TimeReportQueryRequest $request): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->reports->report($context, $request->toDto(), false)));
    }

    /**
     * 只回指定成員的分頁明細
     *
     * @param  TimeReportQueryRequest  $request  驗證的明細條件
     * @return ApiResponse 期間、總計與分頁明細
     */
    public function details(TimeReportQueryRequest $request): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->reports->report($context, $request->toDto(), true)));
    }

    /**
     * 搜尋分頁專案或成員選項
     *
     * @param  PageQueryRequest  $request  有界條件
     * @param  string  $kind  路由限定種類
     * @return ApiResponse 最小選項頁
     */
    public function options(PageQueryRequest $request, string $kind): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->reports->options($context->legacyIdentity(), $kind, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], $data['page']->items()));
    }
}
