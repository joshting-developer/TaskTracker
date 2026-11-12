<?php

namespace App\Http\Controllers;

use App\Http\RealtimeSession;
use App\Http\Requests\JsonOperationRequest;
use App\Http\Resources\ApiResponse;

/** Reverb設定及私有頻道HTTP入口；授權與簽章交由各自邊界負責。 */
class RealtimeController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  RealtimeSession  $realtime  此Controller的穩定協作者
     */
    public function __construct(private RealtimeSession $realtime) {}

    /**
     * 回傳前端連線Reverb需要的公開設定；未啟用時回停用狀態。
     *
     * @param  JsonOperationRequest  $request  JSON請求
     * @return ApiResponse 公開的連線設定
     */
    public function config(JsonOperationRequest $request): ApiResponse
    {
        $request->payload();

        return new ApiResponse($this->realtime->configuration($request));
    }

    /**
     * 在交易內重驗Session與Project權限後，簽發私有頻道訂閱授權。
     *
     * @param  JsonOperationRequest  $request  JSON請求
     * @return ApiResponse 私有頻道簽章
     */
    public function authorizeChannel(JsonOperationRequest $request): ApiResponse
    {
        return new ApiResponse($this->realtime->authorize($request, $request->payload()));
    }
}
