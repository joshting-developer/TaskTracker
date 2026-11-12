<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供工作空間快照、邀請與示範資料HTTP入口。
 */
class WorkspaceController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceRpc  $rpc  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceRpc $rpc) {}

    /**
     * 驗證JSON後查閱可見工作空間快照，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function show(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'getWorkspace', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後邀請同事加入工作空間，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function invite(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'inviteMember', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後載入示範專案，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function demo(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'loadDemo', $input);

        return new ApiResponse($result);
    }
}
