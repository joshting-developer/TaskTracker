<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 統一成功回應格式，保留既有ok／data與CSRF契約。
 */
class ApiResponse extends JsonResource
{
    public static $wrap = null;

    /**
     * 設定已授權回應資料與CSRF標頭選項。
     *
     * @param  mixed  $resource  已完成授權的回傳資料
     * @param  bool  $includeCsrf  是否附上目前Session的CSRF token
     * @return void
     */
    public function __construct(mixed $resource, private bool $includeCsrf = false)
    {
        parent::__construct($resource);
    }

    /**
     * 將已授權的資料包成既有成功回應。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'ok' => true,
            'data' => $this->resource,
        ];
    }

    /**
     * 帳號操作成功時附上目前CSRF，避免Session輪替後使用舊token。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  JsonResponse  $response  即將傳送的JSON回應
     * @return void
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        if ($this->includeCsrf) {
            $response->header('X-CSRF-TOKEN', csrf_token());
        }
    }
}
