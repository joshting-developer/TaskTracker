<?php

namespace App\Http\Controllers;

use App\Http\Requests\JsonOperationRequest;
use App\Http\Requests\Writes\TodoWriteRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供個人／交辦待辦、通知及轉卡HTTP入口。
 */
class TodoController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceRpc  $rpc  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceRpc $rpc) {}

    /**
     * 驗證JSON後查閱本人參與的待辦，回傳既有API格式。
     *
     * @param  JsonOperationRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function index(JsonOperationRequest $request): ApiResponse
    {
        $input = $request->payload();
        $result = $this->rpc->execute($request, 'getTodos', $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後建立自記或交辦待辦，回傳既有API格式。
     *
     * @param  TodoWriteRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function store(TodoWriteRequest $request): ApiResponse
    {
        $input = $request->toDto();
        $result = $this->rpc->executeData($request, $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後修改自己交辦的內容，回傳既有API格式。
     *
     * @param  TodoWriteRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function update(TodoWriteRequest $request): ApiResponse
    {
        $input = $request->toDto();
        $result = $this->rpc->executeData($request, $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後完成目前承辦的待辦，回傳既有API格式。
     *
     * @param  TodoWriteRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function complete(TodoWriteRequest $request): ApiResponse
    {
        $input = $request->toDto();
        $result = $this->rpc->executeData($request, $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後重新開啟已完成待辦，回傳既有API格式。
     *
     * @param  TodoWriteRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function reopen(TodoWriteRequest $request): ApiResponse
    {
        $input = $request->toDto();
        $result = $this->rpc->executeData($request, $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後將交辦退回原交辦者，回傳既有API格式。
     *
     * @param  TodoWriteRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function returnToCreator(TodoWriteRequest $request): ApiResponse
    {
        $input = $request->toDto();
        $result = $this->rpc->executeData($request, $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後取消自己交辦的待辦，回傳既有API格式。
     *
     * @param  TodoWriteRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function cancel(TodoWriteRequest $request): ApiResponse
    {
        $input = $request->toDto();
        $result = $this->rpc->executeData($request, $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後單向轉成正式卡片，回傳既有API格式。
     *
     * @param  TodoWriteRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function convert(TodoWriteRequest $request): ApiResponse
    {
        $input = $request->toDto();
        $result = $this->rpc->executeData($request, $input);

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後標記本人的待辦通知已讀，回傳既有API格式。
     *
     * @param  TodoWriteRequest  $request  目前HTTP請求與Session
     * @return ApiResponse
     */
    public function readNotification(TodoWriteRequest $request): ApiResponse
    {
        $input = $request->toDto();
        $result = $this->rpc->executeData($request, $input);

        return new ApiResponse($result);
    }
}
