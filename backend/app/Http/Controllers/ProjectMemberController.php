<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供Project成員及授權異動HTTP入口。
 */
class ProjectMemberController extends Controller
{
    /**
     * 驗證JSON後查閱專案成員與可授予角色，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function index(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'getProjectMembers', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後加入成員或更新角色權限組合，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function save(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'setProjectMember', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後撤回專案成員資格，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function remove(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'removeProjectMember', $input);

        return new ApiResponse($result);
    }
}
