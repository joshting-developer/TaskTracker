<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Resources\ApiResponse;
use Illuminate\Http\Request;

/** 第一個純Bearer身分入口；未開放現有Session RPC。 */
class TokenIdentityController extends Controller
{
    /** 注入不依賴瀏覽器Session的認證／資格邊界。 */
    public function __construct(private BearerTokenSession $tokens) {}

    /**
     * 以PAT回傳本人及固定Workspace身分。
     *
     * @param  Request  $request  HTTP Authorization header
     * @return ApiResponse 安全身分投影
     */
    public function show(Request $request): ApiResponse
    {
        return new ApiResponse($this->tokens->identity($request));
    }
}
