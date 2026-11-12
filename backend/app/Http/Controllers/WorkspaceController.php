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
     * 驗證JSON後查閱可見工作空間快照，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function show(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'getWorkspace', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後邀請同事加入工作空間，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function invite(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'inviteMember', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後載入示範專案，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function demo(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'loadDemo', $input);

        return new ApiResponse($result);
    }
}
