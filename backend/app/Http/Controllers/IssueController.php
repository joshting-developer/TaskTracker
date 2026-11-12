<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供任務建立、更新及留言HTTP入口。
 */
class IssueController extends Controller
{
    /**
     * 驗證JSON後建立任務及必要關聯，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function store(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'createIssue', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後更新任務內容與關聯，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function update(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'updateIssue', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後新增任務留言，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function comment(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'addComment', $input);

        return new ApiResponse($result);
    }
}
