<?php

use App\Http\Controllers\Integrations\ApiSpecificationController;
use App\Http\Controllers\Integrations\PersonalTokensController;
use App\Http\Controllers\Integrations\TokenIdentityController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/api/v1/personal-token-audits', [PersonalTokensController::class, 'audits'])->block(10, 10);
Route::get('/api/v1/personal-tokens', [PersonalTokensController::class, 'index'])->block(10, 10);
Route::post('/api/v1/personal-tokens', [PersonalTokensController::class, 'store'])->middleware('throttle:personal-token-create')->block(10, 10);
Route::post('/api/v1/personal-tokens/{token}/revoke', [PersonalTokensController::class, 'revoke'])->middleware('throttle:personal-token-revoke')->block(10, 10);
Route::get('/api/public/v1/me', [TokenIdentityController::class, 'show'])->middleware('throttle:public-api-v1')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
]);
Route::get('/api/public/openapi.json', [ApiSpecificationController::class, 'show'])->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
]);

Route::get('/api/public/contract.md', [ApiSpecificationController::class, 'contract'])->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
]);
