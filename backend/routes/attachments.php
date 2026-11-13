<?php

use App\Http\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;

// Loaded inside the existing Session v1 group; never a public PAT capability.
Route::get('/projects/{project}/attachments/limits', [AttachmentController::class, 'limits'])->name('v1.attachments.limits');
Route::post('/projects/{project}/attachments', [AttachmentController::class, 'upload'])->middleware('throttle:30,1')->name('v1.attachments.upload');
Route::get('/projects/{project}/issues/{issue}/attachments', [AttachmentController::class, 'index'])->name('v1.attachments.index');
Route::get('/projects/{project}/attachments/{attachment}/content', [AttachmentController::class, 'content'])->name('v1.attachments.content');
Route::post('/projects/{project}/attachments/{attachment}/bind', [AttachmentController::class, 'bind'])->name('v1.attachments.bind');
Route::delete('/projects/{project}/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('v1.attachments.destroy');
