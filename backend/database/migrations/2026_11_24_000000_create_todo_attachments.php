<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TT-294：紙條私有圖片獨立成表, 不放寬Issue附件的Project外鍵；讀取資格依紙條經手者判斷 */
return new class extends Migration
{
    /** 建立紙條附件資料表
     * @return void
     */
    public function up(): void
    {
        Schema::create('todo_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('todo_id')->nullable();
            $table->uuid('uploaded_by');
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['workspace_id', 'todo_id'])->references(['workspace_id', 'id'])->on('todos')->restrictOnDelete();
            $table->string('storage_disk', 50);
            $table->string('storage_key', 255);
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('byte_size');
            $table->string('original_name', 200);
            $table->char('checksum', 64);
            $table->string('status', 20);
            $table->uuid('upload_request_id');
            $table->char('upload_signature', 64);
            $table->dateTime('expires_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['workspace_id', 'uploaded_by', 'upload_request_id'], 'todo_attachments_upload_unique');
            $table->index(['workspace_id', 'todo_id', 'status'], 'todo_attachments_todo');
            $table->index(['workspace_id', 'uploaded_by', 'status'], 'todo_attachments_pending_quota');
            $table->index(['status', 'expires_at', 'id'], 'todo_attachments_expiry');
        });
    }

    /** 移除紙條附件資料表
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('todo_attachments');
    }
};
