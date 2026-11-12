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
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceRpc  $rpc  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceRpc $rpc) {}

    /**
     * 驗證JSON後查閱SMTP設定狀態，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function status(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'smtpStatus', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後驗證SMTP連線而不寄信，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function verify(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'verifySmtp', $input);

        return new ApiResponse($result);
    }
}
