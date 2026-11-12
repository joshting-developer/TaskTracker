<?php

namespace App\Http\Controllers;

use App\Http\RealtimeSession;
use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;

/** Reverb設定及私有頻道HTTP入口；授權與簽章交由各自邊界負責。 */
class RealtimeController extends Controller
{
    /**
     * 回傳前端連線Reverb需要的公開設定；未啟用時回停用狀態。
     *
     * @param  JsonOperationRequest  $request  JSON請求
     * @param  RealtimeSession  $realtime  Session與協定協調
     * @return ApiResponse 公開的連線設定
     */
    public function config(JsonOperationRequest $request, RealtimeSession $realtime): ApiResponse
    {
        $request->payload();

        return new ApiResponse($realtime->configuration($request));
    }

    /**
     * 在交易內重驗Session與Project權限後，簽發私有頻道訂閱授權。
     *
     * @param  JsonOperationRequest  $request  JSON請求
     * @param  RealtimeSession  $realtime  同交易授權及簽章協調
     * @return ApiResponse 私有頻道簽章
     */
    public function authorizeChannel(JsonOperationRequest $request, RealtimeSession $realtime): ApiResponse
    {
        return new ApiResponse($realtime->authorize($request, $request->payload()));
    }
}
