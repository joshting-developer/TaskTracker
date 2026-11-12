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
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceRpc  $rpc  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceRpc $rpc) {}

    /**
     * 驗證JSON後查閱專案成員與可授予角色，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function index(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'getProjectMembers', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後加入成員或更新角色權限組合，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function save(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'setProjectMember', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後撤回專案成員資格，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function remove(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'removeProjectMember', $input);

        return new ApiResponse($result);
    }
}
