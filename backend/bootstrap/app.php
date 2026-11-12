<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\ApiBoundary::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Domain\DomainError $error, Request $request) {
            $status = match ($error->errorCode) {
                'UNAUTHENTICATED' => 401, 'FORBIDDEN' => 403, 'NOT_FOUND' => 404,
                'WORKSPACE_CHANGED', 'REVISION_CONFLICT', 'REQUEST_CONFLICT', 'DUPLICATE_CODE', 'DEMO_EXISTS', 'CONFLICT' => 409,
                'SMTP_ERROR' => 502, 'RECEIPT_UNREADABLE' => 500, default => 400,
            };
            return response()->json(['ok' => false, 'error' => ['code' => $error->errorCode, 'message' => $error->getMessage()]], $status)->header('Cache-Control', 'no-store');
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error, Request $request) {
            if (!$request->is('api/*')) return null;
            $status = $error->getStatusCode();
            $headers = array_filter($error->getHeaders(), fn ($name) => in_array(strtolower($name), ['retry-after', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset', 'allow'], true), ARRAY_FILTER_USE_KEY);
            return response()->json(['ok' => false, 'error' => ['code' => match ($status) { 419 => 'CSRF_EXPIRED', 429 => 'RATE_LIMIT', 404 => 'NOT_FOUND', 405 => 'METHOD_NOT_ALLOWED', default => 'INVALID_INPUT' }, 'message' => match ($status) { 419 => '登入表單已過期, 請重試', 429 => '操作太頻繁, 請稍後再試', 404 => '找不到這個操作', 405 => '不支援這個HTTP方法', default => '請求無法處理' }]], $status)->withHeaders($headers)->header('Cache-Control', 'no-store');
        });
        $exceptions->render(function (\Throwable $error, Request $request) {
            if (! $request->is('api/public/v1/*')) return null;
            return response()->json(['ok' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => '服務暫時無法處理請求']], 500)->header('Cache-Control', 'no-store');
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

if ($envPath = getenv('TASKTRACKER_ENV_PATH')) $app->useEnvironmentPath($envPath);
return $app;
