<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\Queries\BoardsController;
use App\Http\Controllers\Queries\IssuesController;
use App\Http\Controllers\Queries\OperationLogsController;
use App\Http\Controllers\Queries\ProjectMembersController;
use App\Http\Controllers\Queries\ProjectNotificationsController;
use App\Http\Controllers\Queries\ProjectsController;
use App\Http\Controllers\Queries\ProjectTimeController;
use App\Http\Controllers\Queries\TimeReportsController;
use App\Http\Controllers\Queries\TodosController;
use App\Http\Controllers\Queries\WorkspaceSettingsController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\RecordHttpOperation;
use App\Services\AccountSettings;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// 存活探針不建立Session、不發CSRF cookie，也不觸碰業務資料庫。
Route::get('/api/health', [SystemController::class, 'health'])->name('system.health')->withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
]);
Route::get('/api/csrf-cookie', [SystemController::class, 'csrf'])->name('system.csrf');
Route::post('/api/realtime/config', [RealtimeController::class, 'config'])->name('realtime.config');
Route::post('/api/realtime/authorize', [RealtimeController::class, 'authorizeChannel'])->name('realtime.authorize')->middleware('throttle:120,1');

$authThrottle = fn (int $perMinute) => 'throttle:'.($perMinute * config('tasktracker.auth_throttle_scale', 1)).',1';
Route::post('/api/auth/verify-email', [EmailVerificationController::class, 'verify'])->name('auth.verify-email')->middleware($authThrottle(10));
Route::post('/api/auth/resend-verification', [EmailVerificationController::class, 'resend'])->name('auth.resend-verification')->middleware($authThrottle(5));

Route::get('/api/auth/registration-policy', [AuthController::class, 'registrationPolicy'])->name('auth.registration-policy')->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class]);

Route::post('/api/auth/register', [AuthController::class, 'register'])->name('auth.register')->middleware($authThrottle(20));
Route::post('/api/auth/login', [AuthController::class, 'login'])->name('auth.login')->middleware($authThrottle(20));
Route::post('/api/auth/current', [AuthController::class, 'current'])->name('auth.current');
Route::post('/api/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

foreach (AccountSettings::OPERATIONS as $operation) {
    Route::post('/api/rpc/'.$operation, [AccountSettingsController::class, 'execute'])->defaults('operation', $operation)->name('rpc.'.$operation);
}

Route::post('/api/rpc/getWorkspace', [WorkspaceController::class, 'show'])->name('rpc.getWorkspace');
Route::post('/api/rpc/inviteMember', [WorkspaceController::class, 'invite'])->name('rpc.inviteMember');
Route::post('/api/rpc/loadDemo', [WorkspaceController::class, 'demo'])->name('rpc.loadDemo');

Route::post('/api/rpc/createProject', [ProjectController::class, 'store'])->name('rpc.createProject');
Route::post('/api/rpc/updateProject', [ProjectController::class, 'update'])->name('rpc.updateProject');
Route::post('/api/rpc/updateStatuses', [ProjectController::class, 'statuses'])->name('rpc.updateStatuses');

Route::post('/api/rpc/createIssue', [IssueController::class, 'store'])->name('rpc.createIssue');
Route::post('/api/rpc/updateIssue', [IssueController::class, 'update'])->name('rpc.updateIssue');
Route::post('/api/rpc/addComment', [IssueController::class, 'comment'])->name('rpc.addComment');
Route::post('/api/rpc/deleteIssue', [IssueController::class, 'destroy'])->name('rpc.deleteIssue');

Route::post('/api/rpc/getTimeReport', [TimeEntryController::class, 'report'])->name('rpc.getTimeReport');
Route::post('/api/rpc/logTime', [TimeEntryController::class, 'store'])->name('rpc.logTime');
Route::post('/api/rpc/updateTime', [TimeEntryController::class, 'update'])->name('rpc.updateTime');
Route::post('/api/rpc/voidTime', [TimeEntryController::class, 'invalidate'])->name('rpc.voidTime');

Route::post('/api/rpc/createBoard', [BoardController::class, 'store'])->name('rpc.createBoard');
Route::post('/api/rpc/updateBoard', [BoardController::class, 'update'])->name('rpc.updateBoard');
Route::post('/api/rpc/archiveBoard', [BoardController::class, 'archive'])->name('rpc.archiveBoard');
Route::post('/api/rpc/restoreBoard', [BoardController::class, 'restore'])->name('rpc.restoreBoard');

Route::post('/api/rpc/getProjectMembers', [ProjectMemberController::class, 'index'])->name('rpc.getProjectMembers');
Route::post('/api/rpc/setProjectMember', [ProjectMemberController::class, 'save'])->name('rpc.setProjectMember');
Route::post('/api/rpc/removeProjectMember', [ProjectMemberController::class, 'remove'])->name('rpc.removeProjectMember');

Route::post('/api/rpc/getTodos', [TodoController::class, 'index'])->name('rpc.getTodos');
Route::post('/api/rpc/commentTodo', [TodoController::class, 'collaborate'])->name('rpc.commentTodo');
Route::post('/api/rpc/forwardTodo', [TodoController::class, 'collaborate'])->name('rpc.forwardTodo');
Route::post('/api/rpc/createTodo', [TodoController::class, 'store'])->name('rpc.createTodo');
Route::post('/api/rpc/updateTodo', [TodoController::class, 'update'])->name('rpc.updateTodo');
Route::post('/api/rpc/completeTodo', [TodoController::class, 'complete'])->name('rpc.completeTodo');
Route::post('/api/rpc/reopenTodo', [TodoController::class, 'reopen'])->name('rpc.reopenTodo');
Route::post('/api/rpc/returnTodo', [TodoController::class, 'returnToCreator'])->name('rpc.returnTodo');
Route::post('/api/rpc/cancelTodo', [TodoController::class, 'cancel'])->name('rpc.cancelTodo');
Route::post('/api/rpc/convertTodo', [TodoController::class, 'convert'])->name('rpc.convertTodo');
Route::post('/api/rpc/readTodoNotification', [TodoController::class, 'readNotification'])->name('rpc.readTodoNotification');

Route::post('/api/rpc/smtpStatus', [SmtpController::class, 'status'])->name('rpc.smtpStatus');
Route::post('/api/rpc/verifySmtp', [SmtpController::class, 'verify'])->name('rpc.verifySmtp');

// v1先共用Session／CSRF可信邊界，Application Service可供後續PAT適配器沿用。
Route::prefix('/api/v1')->group(function () {
    require __DIR__.'/attachments.php';

    Route::get('/time-report', [TimeReportsController::class, 'index'])->name('v1.time-report');
    Route::get('/time-report/details', [TimeReportsController::class, 'details'])->name('v1.time-report.details');
    Route::get('/time-report/options/{kind}', [TimeReportsController::class, 'options'])->whereIn('kind', ['projects', 'members'])->name('v1.time-report.options');
    Route::get('/workspace/settings', [WorkspaceSettingsController::class, 'show'])->name('v1.workspace.settings');
    Route::get('/workspace/members', [WorkspaceSettingsController::class, 'members'])->name('v1.workspace.members');
    Route::get('/operation-logs', [OperationLogsController::class, 'index'])->name('v1.operation-logs');
    Route::get('/workspace/member-roles', [WorkspaceSettingsController::class, 'roles'])->name('v1.workspace.member-roles');
    Route::get('/personal-version', [TodosController::class, 'versions'])->name('v1.personal-version');
    Route::get('/todos/counts', [TodosController::class, 'counts'])->name('v1.todos.counts');
    Route::get('/todos', [TodosController::class, 'index'])->name('v1.todos');
    Route::get('/todos/{todo}/comments', [TodosController::class, 'events'])->defaults('kind', 'comments')->name('v1.todos.comments');
    Route::get('/todos/{todo}/handoffs', [TodosController::class, 'events'])->defaults('kind', 'handoffs')->name('v1.todos.handoffs');
    Route::get('/todos/{todo}', [TodosController::class, 'show'])->name('v1.todos.show');
    Route::get('/todos/{todo}/history', [TodosController::class, 'history'])->name('v1.todos.history');
    Route::get('/todo-assignees', [TodosController::class, 'assignees'])->name('v1.todo-assignees');
    Route::get('/todo-notifications/unread', [TodosController::class, 'unread'])->name('v1.todo-notifications.unread');
    Route::get('/todo-notifications', [TodosController::class, 'notifications'])->name('v1.todo-notifications');
    Route::get('/projects/{project}/boards', [BoardsController::class, 'index'])->name('v1.projects.boards');
    Route::get('/projects/{project}/boards/{board}', [BoardsController::class, 'show'])->name('v1.projects.boards.show');
    Route::get('/projects/{project}/boards/{board}/issues', [BoardsController::class, 'issues'])->name('v1.projects.boards.issues');
    Route::get('/projects/{project}/boards/{board}/version', [BoardsController::class, 'version'])->name('v1.projects.boards.version');
    Route::get('/project-lead-options', [ProjectsController::class, 'leads'])->name('v1.project-lead-options');
    Route::get('/project-options', [ProjectsController::class, 'options'])->name('v1.project-options');
    Route::get('/projects', [ProjectsController::class, 'index'])->name('v1.projects');
    Route::get('/projects/{project}', [ProjectsController::class, 'show'])->name('v1.projects.show');
    Route::get('/projects/{project}/status-usage', [ProjectsController::class, 'statusUsage'])->name('v1.projects.status-usage');
    Route::get('/projects/{project}/notifications', [ProjectNotificationsController::class, 'index'])->name('v1.projects.notifications');
    Route::get('/projects/{project}/notifications/{notification}', [ProjectNotificationsController::class, 'show'])->name('v1.projects.notifications.show');
    Route::get('/projects/{project}/time-calendar', [ProjectTimeController::class, 'calendar'])->name('v1.projects.time-calendar');
    Route::get('/projects/{project}/time-entries', [ProjectTimeController::class, 'day'])->name('v1.projects.time-entries');
    Route::get('/projects/{project}/acl', [ProjectMembersController::class, 'metadata'])->name('v1.projects.acl');
    Route::get('/projects/{project}/acl/members', [ProjectMembersController::class, 'index'])->name('v1.projects.acl.members');
    Route::get('/projects/{project}/acl/candidates', [ProjectMembersController::class, 'candidates'])->name('v1.projects.acl.candidates');
    Route::get('/projects/{project}/acl/history', [ProjectMembersController::class, 'history'])->name('v1.projects.acl.history');
    Route::get('/projects/{project}/member-options', [ProjectsController::class, 'members'])->name('v1.projects.member-options');
    Route::get('/issue-locations/{issue}', [IssuesController::class, 'locate'])->name('v1.issue-locations.show');
    Route::get('/projects/{project}/issue-options', [IssuesController::class, 'options'])->name('v1.projects.issue-options');
    Route::get('/projects/{project}/issues', [IssuesController::class, 'index'])->name('v1.projects.issues');
    Route::get('/projects/{project}/issues/{issue}', [IssuesController::class, 'show'])->name('v1.projects.issues.show');
    Route::get('/projects/{project}/issues/{issue}/comments', [IssuesController::class, 'comments'])->name('v1.projects.issues.comments');
    Route::get('/projects/{project}/issues/{issue}/history', [IssuesController::class, 'history'])->name('v1.projects.issues.history');
    Route::get('/projects/{project}/issues/{issue}/children', [IssuesController::class, 'children'])->name('v1.projects.issues.children');
    Route::get('/projects/{project}/issues/{issue}/time', [IssuesController::class, 'time'])->name('v1.projects.issues.time');
});

// 相容舊客戶端的未知／已停用操作，維持身分驗證及JSON錯誤契約。
Route::post('/api/rpc/{operation}', [ApiController::class, 'unknown'])->where('operation', '[A-Za-z]+')->name('rpc.unknown');

foreach (['workspaces', 'switchWorkspace', 'acceptWorkspaceInvite', 'invitation'] as $operation) {
    Route::post('/api/auth/'.$operation, [AuthController::class, 'workspace'])->defaults('operation', $operation)->name('auth.'.$operation)->middleware('throttle:60,1');
}

// 同一Session切換與業務請求依序處理；MySQL由GuardedStartSession持有連線鎖至保存完成。
// waitsFor仍為10秒；locksFor只供SQLite隔離快測的框架TTL鎖，不作MySQL最長請求時間。
foreach (Route::getRoutes() as $route) {
    if ($route->uri() === 'api/csrf-cookie' || str_starts_with($route->uri(), 'api/v1/') || str_starts_with($route->uri(), 'api/rpc/') || str_starts_with($route->uri(), 'api/auth/') || str_starts_with($route->uri(), 'api/realtime/')) {
        $route->block(10, 10);
    }
}

require __DIR__.'/tokens.php';

// TT-260：所有本專案路由在回應後記錄具名寫入操作；紀錄失敗不影響回應。
foreach (Route::getRoutes() as $route) {
    $route->middleware(RecordHttpOperation::class);
}
