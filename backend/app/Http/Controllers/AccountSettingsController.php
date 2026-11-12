<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/** 提供已註冊設定操作的JSON邊界，授權及交易交由服務處理。 */
class AccountSettingsController extends Controller
{
    /** 執行路由固定的設定動作，不採用body自稱的操作或身分。
     * @param  JsonOperationRequest  $request  JSON請求
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @param  string  $operation  路由預設的固定操作
     * @return ApiResponse
     */
    public function execute(JsonOperationRequest $request, WorkspaceRpc $rpc, string $operation): ApiResponse
    {
        return new ApiResponse($rpc->execute($request, $operation, $request->payload()));
    }
}
