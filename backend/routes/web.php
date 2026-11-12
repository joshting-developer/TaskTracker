<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\Queries\BoardsController;
use App\Http\Controllers\Queries\IssuesController;
use App\Http\Controllers\Queries\ProjectMembersController;
use App\Http\Controllers\Queries\ProjectNotificationsController;
use App\Http\Controllers\Queries\ProjectsController;
use App\Http\Controllers\Queries\ProjectTimeController;
use App\Http\Controllers\Queries\TodosController;
use App\Http\Controllers\Queries\WorkspaceSettingsController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\WorkspaceController;
use App\Services\AccountSettings;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// 存活探針不建立Session、不發CSRF cookie，也不觸碰業務資料庫。
Route::get('/api/health', [SystemController::class, 'health'])->withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
]);
Route::get('/api/csrf-cookie', [SystemController::class, 'csrf']);
Route::post('/api/realtime/config', [RealtimeController::class, 'config']);
Route::post('/api/realtime/authorize', [RealtimeController::class, 'authorizeChannel'])->middleware('throttle:120,1');

Route::post('/api/auth/register', [AuthController::class, 'register'])->middleware('throttle:20,1');
Route::post('/api/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/api/auth/current', [AuthController::class, 'current']);
Route::post('/api/auth/logout', [AuthController::class, 'logout']);

foreach (AccountSettings::OPERATIONS as $operation) {
    Route::post('/api/rpc/'.$operation, [AccountSettingsController::class, 'execute'])->defaults('operation', $operation);
}

Route::post('/api/rpc/getWorkspace', [WorkspaceController::class, 'show']);
Route::post('/api/rpc/inviteMember', [WorkspaceController::class, 'invite']);
Route::post('/api/rpc/loadDemo', [WorkspaceController::class, 'demo']);

Route::post('/api/rpc/createProject', [ProjectController::class, 'store']);
Route::post('/api/rpc/updateProject', [ProjectController::class, 'update']);
Route::post('/api/rpc/updateStatuses', [ProjectController::class, 'statuses']);

Route::post('/api/rpc/createIssue', [IssueController::class, 'store']);
Route::post('/api/rpc/updateIssue', [IssueController::class, 'update']);
Route::post('/api/rpc/addComment', [IssueController::class, 'comment']);

Route::post('/api/rpc/getTimeReport', [TimeEntryController::class, 'report']);
Route::post('/api/rpc/logTime', [TimeEntryController::class, 'store']);
Route::post('/api/rpc/updateTime', [TimeEntryController::class, 'update']);
Route::post('/api/rpc/voidTime', [TimeEntryController::class, 'invalidate']);

Route::post('/api/rpc/createBoard', [BoardController::class, 'store']);
Route::post('/api/rpc/updateBoard', [BoardController::class, 'update']);
Route::post('/api/rpc/archiveBoard', [BoardController::class, 'archive']);

Route::post('/api/rpc/getProjectMembers', [ProjectMemberController::class, 'index']);
Route::post('/api/rpc/setProjectMember', [ProjectMemberController::class, 'save']);
Route::post('/api/rpc/removeProjectMember', [ProjectMemberController::class, 'remove']);

Route::post('/api/rpc/getTodos', [TodoController::class, 'index']);
Route::post('/api/rpc/createTodo', [TodoController::class, 'store']);
Route::post('/api/rpc/updateTodo', [TodoController::class, 'update']);
Route::post('/api/rpc/completeTodo', [TodoController::class, 'complete']);
Route::post('/api/rpc/reopenTodo', [TodoController::class, 'reopen']);
Route::post('/api/rpc/returnTodo', [TodoController::class, 'returnToCreator']);
Route::post('/api/rpc/cancelTodo', [TodoController::class, 'cancel']);
Route::post('/api/rpc/convertTodo', [TodoController::class, 'convert']);
Route::post('/api/rpc/readTodoNotification', [TodoController::class, 'readNotification']);

Route::post('/api/rpc/smtpStatus', [SmtpController::class, 'status']);
Route::post('/api/rpc/verifySmtp', [SmtpController::class, 'verify']);

// v1先共用Session／CSRF可信邊界，Application Service可供後續PAT適配器沿用。
Route::prefix('/api/v1')->group(function () {
    Route::get('/workspace/settings', [WorkspaceSettingsController::class, 'show']);
    Route::get('/workspace/members', [WorkspaceSettingsController::class, 'members']);
    Route::get('/workspace/member-roles', [WorkspaceSettingsController::class, 'roles']);
    Route::get('/personal-version', [TodosController::class, 'versions']);
    Route::get('/todos/counts', [TodosController::class, 'counts']);
    Route::get('/todos', [TodosController::class, 'index']);
    Route::get('/todos/{todo}', [TodosController::class, 'show']);
    Route::get('/todos/{todo}/history', [TodosController::class, 'history']);
    Route::get('/todo-assignees', [TodosController::class, 'assignees']);
    Route::get('/todo-notifications/unread', [TodosController::class, 'unread']);
    Route::get('/todo-notifications', [TodosController::class, 'notifications']);
    Route::get('/projects/{project}/boards', [BoardsController::class, 'index']);
    Route::get('/projects/{project}/boards/{board}', [BoardsController::class, 'show']);
    Route::get('/projects/{project}/boards/{board}/issues', [BoardsController::class, 'issues']);
    Route::get('/projects/{project}/boards/{board}/version', [BoardsController::class, 'version']);
    Route::get('/project-lead-options', [ProjectsController::class, 'leads']);
    Route::get('/project-options', [ProjectsController::class, 'options']);
    Route::get('/projects', [ProjectsController::class, 'index']);
    Route::get('/projects/{project}', [ProjectsController::class, 'show']);
    Route::get('/projects/{project}/status-usage', [ProjectsController::class, 'statusUsage']);
    Route::get('/projects/{project}/notifications', [ProjectNotificationsController::class, 'index']);
    Route::get('/projects/{project}/notifications/{notification}', [ProjectNotificationsController::class, 'show']);
    Route::get('/projects/{project}/time-calendar', [ProjectTimeController::class, 'calendar']);
    Route::get('/projects/{project}/time-entries', [ProjectTimeController::class, 'day']);
    Route::get('/projects/{project}/acl', [ProjectMembersController::class, 'metadata']);
    Route::get('/projects/{project}/acl/members', [ProjectMembersController::class, 'index']);
    Route::get('/projects/{project}/acl/candidates', [ProjectMembersController::class, 'candidates']);
    Route::get('/projects/{project}/acl/history', [ProjectMembersController::class, 'history']);
    Route::get('/projects/{project}/member-options', [ProjectsController::class, 'members']);
    Route::get('/issue-locations/{issue}', [IssuesController::class, 'locate']);
    Route::get('/projects/{project}/issue-options', [IssuesController::class, 'options']);
    Route::get('/projects/{project}/issues', [IssuesController::class, 'index']);
    Route::get('/projects/{project}/issues/{issue}', [IssuesController::class, 'show']);
    Route::get('/projects/{project}/issues/{issue}/comments', [IssuesController::class, 'comments']);
    Route::get('/projects/{project}/issues/{issue}/history', [IssuesController::class, 'history']);
    Route::get('/projects/{project}/issues/{issue}/children', [IssuesController::class, 'children']);
    Route::get('/projects/{project}/issues/{issue}/time', [IssuesController::class, 'time']);
});

// 相容舊客戶端的未知／已停用操作，維持身分驗證及JSON錯誤契約。
Route::post('/api/rpc/{operation}', [ApiController::class, 'unknown'])->where('operation', '[A-Za-z]+');

foreach (['workspaces', 'switchWorkspace', 'acceptWorkspaceInvite', 'invitation'] as $operation) {
    Route::post('/api/auth/'.$operation, [AuthController::class, 'workspace'])->defaults('operation', $operation)->middleware('throttle:60,1');
}

// 同一Session切換與業務請求依序處理；MySQL由GuardedStartSession持有連線鎖至保存完成。
// waitsFor仍為10秒；locksFor只供SQLite隔離快測的框架TTL鎖，不作MySQL最長請求時間。
foreach (Route::getRoutes() as $route) {
    if ($route->uri() === 'api/csrf-cookie' || str_starts_with($route->uri(), 'api/v1/') || str_starts_with($route->uri(), 'api/rpc/') || str_starts_with($route->uri(), 'api/auth/') || str_starts_with($route->uri(), 'api/realtime/')) {
        $route->block(10, 10);
    }
}

require __DIR__.'/tokens.php';
