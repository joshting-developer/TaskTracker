<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * 提供健康檢查與CSRF初始化端點。
 */
class SystemController extends Controller
{
    /**
     * 回傳API存活狀態。
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'tasktracker-laravel',
        ]);
    }

    /**
     * 初始化Session並提供目前CSRF token。
     *
     * @return JsonResponse
     */
    public function csrf(): JsonResponse
    {
        return response()->json(['csrfToken' => csrf_token()]);
    }
}
