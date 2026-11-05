<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\WorkspaceController;
use App\Services\AccountSettings;
use Illuminate\Support\Facades\Route;

Route::get('/api/health', [SystemController::class, 'health']);
Route::get('/api/csrf-cookie', [SystemController::class, 'csrf']);

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

// 相容舊客戶端的未知／已停用操作，維持身分驗證及JSON錯誤契約。
Route::post('/api/rpc/{operation}', [ApiController::class, 'unknown'])->where('operation', '[A-Za-z]+');
