<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 只存成功寫入的來源metadata，識別快照不隨帳號／Token刪除而遺失。 */
return new class extends Migration
{
    /**
     * 新增來源表與本人工作區分頁索引，不改寫既有歷史。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('integration_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            // 歷史識別快照刻意不設User／Token FK，保留刪除前的來源。
            $table->string('actor_user_id', 36);
            $table->string('source', 16);
            $table->string('token_id', 20)->nullable();
            $table->string('token_name', 60)->nullable();
            $table->text('abilities');
            $table->string('operation', 80);
            $table->string('resource_id', 64)->nullable();
            $table->dateTime('occurred_at', 6);
            $table->index(['actor_user_id', 'workspace_id', 'id'], 'integration_audit_owner_page');
        });
    }

    /**
     * 回退時僅移除此新增表。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_audits');
    }
};
