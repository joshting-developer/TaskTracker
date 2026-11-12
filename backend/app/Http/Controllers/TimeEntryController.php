<?php

namespace App\Http\Controllers;

use App\Http\Requests\TimeReportRequest;
use App\Http\Requests\Writes\TimeEntryWriteRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供本人工時建立、修改、作廢及工作空間分析HTTP入口。
 */
class TimeEntryController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceRpc  $rpc  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceRpc $rpc) {}

    /**
     * 驗證JSON並查詢工作空間工時分析，授權與彙整交由領域服務。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse 工作空間報表
     */
    public function report(TimeReportRequest $request): ApiResponse
    {
        $result = $this->rpc->timeReport($request, $request->toDto());

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後填報本人工時，回傳既有API格式。
     *
     * @param  TimeEntryWriteRequest  $request  已檢查型別的寫入輸入
     * @return ApiResponse
     */
    public function store(TimeEntryWriteRequest $request): ApiResponse
    {
        $result = $this->rpc->execute($request, 'logTime', $request->toDto()->input());

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後修改本人工時，回傳既有API格式。
     *
     * @param  TimeEntryWriteRequest  $request  已檢查型別的寫入輸入
     * @return ApiResponse
     */
    public function update(TimeEntryWriteRequest $request): ApiResponse
    {
        $result = $this->rpc->execute($request, 'updateTime', $request->toDto()->input());

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後作廢本人工時，回傳既有API格式。
     *
     * @param  TimeEntryWriteRequest  $request  已檢查型別的寫入輸入
     * @return ApiResponse
     */
    public function invalidate(TimeEntryWriteRequest $request): ApiResponse
    {
        $result = $this->rpc->execute($request, 'voidTime', $request->toDto()->input());

        return new ApiResponse($result);
    }
}
