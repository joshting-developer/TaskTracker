<?php

use App\Http\Controllers\Integrations\ApiSpecificationController;
use App\Http\Controllers\Integrations\DeveloperDocumentationController;
use App\Http\Controllers\Integrations\PersonalTokensController;
use App\Http\Controllers\Integrations\PublicIssueChangesController;
use App\Http\Controllers\Integrations\PublicIssueExternalController;
use App\Http\Controllers\Integrations\PublicIssuesController;
use App\Http\Controllers\Integrations\PublicProjectsController;
use App\Http\Controllers\Integrations\PublicQueriesController;
use App\Http\Controllers\Integrations\PublicTodosController;
use App\Http\Controllers\Integrations\TokenIdentityController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/api/v1/personal-token-audits', [PersonalTokensController::class, 'audits'])->name('v1.personal-token-audits')->block(10, 10);
Route::get('/api/v1/personal-tokens', [PersonalTokensController::class, 'index'])->name('v1.personal-tokens')->block(10, 10);
Route::post('/api/v1/personal-tokens', [PersonalTokensController::class, 'store'])->name('v1.personal-tokens.store')->middleware('throttle:personal-token-create')->block(10, 10);
Route::post('/api/v1/personal-tokens/{token}/revoke', [PersonalTokensController::class, 'revoke'])->name('v1.personal-tokens.revoke')->middleware('throttle:personal-token-revoke')->block(10, 10);
Route::get('/api/public/v1/me', [TokenIdentityController::class, 'show'])->name('public.v1.me')->middleware('throttle:public-api-v1')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
]);
Route::get('/api/public/openapi.json', [ApiSpecificationController::class, 'show'])->name('public.openapi')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
]);

Route::get('/api/public/contract.md', [ApiSpecificationController::class, 'contract'])->name('public.contract')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
]);

Route::prefix('/api/public/v1')->middleware('throttle:public-api-v1')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
])->group(function () {
    $queries = PublicQueriesController::class;
    $projects = PublicProjectsController::class;
    Route::get('/projects', [$projects, 'projects'])->name('public.v1.projects');
    Route::get('/projects/{project}', [$projects, 'project'])->name('public.v1.projects.show');
    Route::get('/projects/{project}/members', [$projects, 'members'])->name('public.v1.projects.members');
    Route::get('/projects/{project}/statuses', [$projects, 'statuses'])->name('public.v1.projects.statuses');
    Route::get('/projects/{project}/issues', [$queries, 'issues'])->name('public.v1.projects.issues');
    Route::get('/projects/{project}/issue-changes', [PublicIssueChangesController::class, 'changes'])->name('public.v1.projects.issue-changes');
    Route::get('/projects/{project}/issue-deletions', [PublicIssueChangesController::class, 'deletions'])->name('public.v1.projects.issue-deletions');
    Route::get('/projects/{project}/issues/{issue}', [$queries, 'issue'])->name('public.v1.projects.issues.show');
    Route::get('/projects/{project}/issues/{issue}/comments', [$queries, 'comments'])->name('public.v1.projects.issues.comments');
    Route::get('/projects/{project}/issues/external/{source}/{externalId}', [PublicIssueExternalController::class, 'show'])->name('public.v1.projects.issues.external.show');
});

Route::prefix('/api/public/v1/projects/{project}/issues')->middleware('throttle:public-api-v1')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
])->group(function () {
    $writes = PublicIssuesController::class;
    Route::post('/', [$writes, 'execute'])->name('public.issues.create');
    Route::patch('/{issue}', [$writes, 'execute'])->name('public.issues.update');
    Route::post('/{issue}/comments', [$writes, 'execute'])->name('public.issues.comment');
    Route::delete('/{issue}', [$writes, 'execute'])->name('public.issues.delete');
    Route::put('/external/{source}/{externalId}', [PublicIssueExternalController::class, 'upsert'])->name('public.issues.upsert');
    Route::post('/external-batch', [PublicIssueExternalController::class, 'batch'])->name('public.issues.batch');
});

// TT-311：小待辦是工作空間層級, 不在Project底下。
Route::prefix('/api/public/v1')->middleware('throttle:public-api-v1')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
])->group(function () {
    $todos = PublicTodosController::class;
    Route::get('/todos', [$todos, 'index'])->name('public.v1.todos');
    Route::get('/todos/{todo}', [$todos, 'show'])->name('public.v1.todos.show');
    Route::get('/todo-assignees', [$todos, 'assignees'])->name('public.v1.todo-assignees');
    Route::post('/todos', [$todos, 'execute'])->name('public.todos.create');
    Route::patch('/todos/{todo}', [$todos, 'execute'])->name('public.todos.update');
    Route::post('/todos/{todo}/complete', [$todos, 'execute'])->name('public.todos.complete');
    Route::post('/todos/{todo}/reopen', [$todos, 'execute'])->name('public.todos.reopen');
    Route::post('/todos/{todo}/return', [$todos, 'execute'])->name('public.todos.return');
});

Route::prefix('/api/public/docs')->withoutMiddleware([
    StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
])->group(function () {
    $docs = DeveloperDocumentationController::class;
    Route::get('/assets/styles.css', [$docs, 'styles'])->name('public.docs.styles');
    Route::get('/examples.{language}', [$docs, 'example'])->where('language', 'py|sh')->name('public.docs.example');
    Route::get('/{page?}', [$docs, 'show'])->name('public.docs.page');
});
