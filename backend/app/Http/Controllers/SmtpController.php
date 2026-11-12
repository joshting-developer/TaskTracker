<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供SMTP狀態與連線驗證HTTP入口。
 */
class SmtpController extends Controller
{
    /**
     * 驗證JSON後查閱SMTP設定狀態，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function status(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'smtpStatus', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後驗證SMTP連線而不寄信，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @param  WorkspaceRpc  $rpc  舊RPC分派與交易邊界
     * @return ApiResponse
     */
    public function verify(JsonOperationRequest $request, WorkspaceRpc $rpc): ApiResponse
    {
        $input = $request->payload();
        $result = $rpc->execute($request, 'verifySmtp', $input);

        return new ApiResponse($result);
    }
}
