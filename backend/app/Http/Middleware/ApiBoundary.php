<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 限制API請求來源並設定防快取與內容類型保護標頭。
 */
class ApiBoundary
{
    /**
     * 驗證請求來源並設定API回應保護標頭。
     *
     * @param  Request  $request  目前HTTP請求與Session
     * @param  Closure  $next  後續HTTP處理程序
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*')) {
            $origin = $request->header('Origin');
            if ($origin && ! in_array($origin, config('tasktracker.origins'), true) || $request->header('Sec-Fetch-Site') === 'cross-site') {
                return response()->json([
                    'ok' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => '不允許這個請求來源',
                    ],
                ], 403);
            }
        }
        $response = $next($request);
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }
}
