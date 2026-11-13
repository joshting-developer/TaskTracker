<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 背景信件的唯一事件與加密傳輸內容, 不存SMTP憑證 */
return new class extends Migration
{
    /** 建立可去重、重派及查詢狀態的outbox
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('mail_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('event_key', 64)->unique();
            $table->string('kind', 32);
            $table->text('recipient');
            $table->longText('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['status', 'next_attempt_at', 'id'], 'mail_deliveries_dispatch_idx');
            $table->index(['user_id', 'created_at', 'id'], 'mail_deliveries_user_idx');
        });
    }

    /** 只在明確回退時移除本表
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_deliveries');
    }
};
