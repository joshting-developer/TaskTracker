<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 私有附件只存metadata, 範圍外鍵防止跨租戶綁定 */
return new class extends Migration
{
    /** 建立私有附件metadata與範圍索引
     * @return void
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('issue_id')->nullable();
            $table->uuid('uploaded_by');
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['workspace_id', 'project_id'])->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'issue_id'])->references(['workspace_id', 'project_id', 'id'])->on('issues')->restrictOnDelete();
            $table->string('storage_disk', 50);
            $table->string('storage_key', 255);
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('byte_size');
            $table->string('original_name', 200);
            $table->char('checksum', 64);
            $table->string('status', 20);
            $table->uuid('upload_request_id');
            $table->char('upload_signature', 64);
            $table->unsignedInteger('revision')->default(1);
            $table->dateTime('expires_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['workspace_id', 'uploaded_by', 'upload_request_id'], 'attachments_upload_unique');
            $table->index(['workspace_id', 'project_id', 'issue_id', 'status', 'id'], 'attachments_issue_page');
            $table->index(['workspace_id', 'uploaded_by', 'status'], 'attachments_pending_quota');
            $table->index(['status', 'expires_at', 'id'], 'attachments_expiry');
        });
    }

    /** 移除本遷移建立的附件資料表
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
