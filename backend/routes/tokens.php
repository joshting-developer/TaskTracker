<?php

use App\Http\Controllers\Integrations\ApiSpecificationController;
use App\Http\Controllers\Integrations\DeveloperDocumentationController;
use App\Http\Controllers\Integrations\PersonalTokensController;
use App\Http\Controllers\Integrations\PublicIssueChangesController;
use App\Http\Controllers\Integrations\PublicIssueExternalController;
use App\Http\Controllers\Integrations\PublicIssuesController;
use App\Http\Controllers\Integrations\PublicProjectsController;
use App\Http\Controllers\Integrations\PublicQueriesController;
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

Route::prefix('/api/public/v1')->middleware('throttle:public-api-v1')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
])->group(function () {
    $queries = PublicQueriesController::class;
    $projects = PublicProjectsController::class;
    Route::get('/projects', [$projects, 'projects']);
    Route::get('/projects/{project}', [$projects, 'project']);
    Route::get('/projects/{project}/members', [$projects, 'members']);
    Route::get('/projects/{project}/statuses', [$projects, 'statuses']);
    Route::get('/projects/{project}/issues', [$queries, 'issues']);
    Route::get('/projects/{project}/issue-changes', [PublicIssueChangesController::class, 'changes']);
    Route::get('/projects/{project}/issues/{issue}', [$queries, 'issue']);
    Route::get('/projects/{project}/issues/{issue}/comments', [$queries, 'comments']);
    Route::get('/projects/{project}/issues/external/{source}/{externalId}', [PublicIssueExternalController::class, 'show']);
});

Route::prefix('/api/public/v1/projects/{project}/issues')->middleware('throttle:public-api-v1')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
])->group(function () {
    $writes = PublicIssuesController::class;
    Route::post('/', [$writes, 'execute'])->name('public.issues.create');
    Route::patch('/{issue}', [$writes, 'execute'])->name('public.issues.update');
    Route::post('/{issue}/comments', [$writes, 'execute'])->name('public.issues.comment');
    Route::put('/external/{source}/{externalId}', [PublicIssueExternalController::class, 'upsert'])->name('public.issues.upsert');
    Route::post('/external-batch', [PublicIssueExternalController::class, 'batch'])->name('public.issues.batch');
});

Route::prefix('/api/public/docs')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
])->group(function () {
    $docs = DeveloperDocumentationController::class;
    Route::get('/assets/styles.css', [$docs, 'styles']);
    Route::get('/examples.{language}', [$docs, 'example'])->where('language', 'py|sh');
    Route::get('/{page?}', [$docs, 'show']);
});
