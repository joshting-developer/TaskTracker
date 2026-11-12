<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** 只提供已交付的正式OpenAPI；規格不包含尚未開放的任務API。 */
class ApiSpecificationController extends Controller
{
    /** @return JsonResponse 專案單一來源的正式OpenAPI，不包含秘密或未開放端點。 */
    public function show(): JsonResponse
    {
        return response()->json(json_decode(file_get_contents(resource_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR));
    }
}
