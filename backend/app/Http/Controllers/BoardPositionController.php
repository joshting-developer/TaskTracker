<?php

namespace App\Http\Controllers;

use App\Data\WorkspaceContext;
use App\Http\Requests\Writes\BoardPositionRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceTransaction;
use App\Services\BoardPositionService;

/** TT-428：看板卡片手動排序入口 */
class BoardPositionController extends Controller
{
    /** 注入 HTTP 交易與排序用例
     * @param  WorkspaceTransaction  $workspace  鎖後重驗租戶及 Session
     * @param  BoardPositionService  $positions  排序服務
     */
    public function __construct(private WorkspaceTransaction $workspace, private BoardPositionService $positions) {}

    /** 以可信身分改寫欄位內順序
     * @param  BoardPositionRequest  $request  已驗證傳輸
     * @return ApiResponse 新位置
     */
    public function update(BoardPositionRequest $request): ApiResponse
    {
        return new ApiResponse($this->workspace->write($request, fn (WorkspaceContext $context) => $this->positions->reorder($context, $request->payload())));
    }
}
