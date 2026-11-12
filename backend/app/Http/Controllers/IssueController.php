<?php

namespace App\Http\Controllers;

use App\Http\Requests\Writes\IssueWriteRequest;
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
     * @param  IssueWriteRequest  $request  已檢查型別的寫入輸入
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function store(IssueWriteRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $result = $rpc->execute($request, 'createIssue', $request->toDto()->input());

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後更新任務內容與關聯，回傳既有API格式。
     *
     * @param  IssueWriteRequest  $request  已檢查型別的寫入輸入
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function update(IssueWriteRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $result = $rpc->execute($request, 'updateIssue', $request->toDto()->input());

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後新增任務留言，回傳既有API格式。
     *
     * @param  IssueWriteRequest  $request  已檢查型別的寫入輸入
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function comment(IssueWriteRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $result = $rpc->execute($request, 'addComment', $request->toDto()->input());

        return new ApiResponse($result);
    }
}
