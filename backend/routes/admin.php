<?php

use App\Http\Controllers\AdminSessionsController;
use App\Http\Controllers\AdminStatisticsController;
use Illuminate\Support\Facades\Route;

// web middleware沿用Session／CSRF, 不繞過來源限制, 不提供Bearer替代登入
Route::prefix('api/admin')->name('admin.')->group(function () {
    Route::post('session', [AdminSessionsController::class, 'store'])->name('login')->middleware('throttle:5,1')->block(10, 10);
    Route::get('session', [AdminSessionsController::class, 'show'])->name('current')->block(10, 10);
    Route::delete('session', [AdminSessionsController::class, 'destroy'])->name('logout')->block(10, 10);
    Route::get('statistics', [AdminStatisticsController::class, 'index'])->name('statistics')->middleware('throttle:30,1')->block(10, 10);
    Route::get('workspaces', [AdminStatisticsController::class, 'workspaces'])->name('workspaces')->middleware('throttle:30,1')->block(10, 10);
});
