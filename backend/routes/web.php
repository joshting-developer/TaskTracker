<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

Route::get('/api/health', fn () => response()->json(['ok' => true, 'service' => 'tasktracker-laravel']));
Route::get('/api/csrf-cookie', fn () => response()->json(['csrfToken' => csrf_token()]));
Route::prefix('api')->group(function () {
    foreach (['register', 'login', 'current', 'logout'] as $operation) {
        $route = Route::post('/auth/'.$operation, [ApiController::class, 'auth'])->defaults('operation', $operation);
        if (in_array($operation, ['register', 'login'], true)) $route->middleware('throttle:20,1');
    }
    Route::post('/rpc/{operation}', [ApiController::class, 'rpc'])->where('operation', '[A-Za-z]+');
});
