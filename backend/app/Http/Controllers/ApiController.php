<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 處理未知或已停用操作，保留舊API錯誤及登入契約。
 */
class ApiController extends Controller
{
    /**
     * 對未知或已停用操作套用一致的登入與JSON錯誤契約。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  string  $operation  已允許的操作名稱
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function unknown(JsonOperationRequest $request, string $operation, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();

        return new ApiResponse($rpc->execute($request, $operation, $input));
    }
}
