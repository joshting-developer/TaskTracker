<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** 只提供已交付的正式OpenAPI；規格不包含尚未開放的任務API。 */
class ApiSpecificationController extends Controller
{
    /**
     * 匿名提供正式OpenAPI規格。
     *
     * @return JsonResponse 專案單一來源的正式OpenAPI，不包含秘密或未開放端點。
     */
    public function show(): JsonResponse
    {
        return response()->json(json_decode(file_get_contents(resource_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR))->header('Link', '</api/public/docs>; rel="describedby"');
    }

    /**
     * 匿名提供正式版本／認證／錯誤與重試契約，不列未實作端點。
     *
     * @return Response 固定Markdown，不讀取登入或業務資料
     */
    public function contract(): Response
    {
        return response(file_get_contents(resource_path('public-api-contract.md')))->header('Content-Type', 'text/markdown; charset=UTF-8');
    }
}
