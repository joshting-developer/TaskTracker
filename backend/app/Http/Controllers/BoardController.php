<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供看板建立、收錄更新與移除HTTP入口。
 */
class BoardController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceRpc  $rpc  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceRpc $rpc) {}

    /**
     * 驗證JSON後建立自訂看板，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function store(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'createBoard', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後更新看板收錄，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function update(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'updateBoard', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後移除看板並保留任務，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function archive(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'archiveBoard', $input);

        return new ApiResponse($result);
    }
}
