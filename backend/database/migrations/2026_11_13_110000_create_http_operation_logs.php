<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TT-260：回應後觀測用的HTTP寫入操作紀錄；不存密碼／Token／Cookie／request body，也不取代同交易的業務歷史。 */
return new class extends Migration
{
    /**
     * 建立以Workspace公開ID分頁的紀錄表；身分欄為快照不設FK，紀錄寫入不能因關聯失敗而拖累請求。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('http_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_public_id', 36)->nullable();
            $table->string('user_id', 36)->nullable();
            $table->string('token_id', 20)->nullable();
            $table->string('route_name', 80);
            $table->string('method', 8);
            $table->unsignedSmallInteger('status');
            $table->string('project_public_id', 64)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->dateTime('occurred_at', 6);
            $table->index(['workspace_public_id', 'id'], 'http_operation_logs_workspace_page');
            $table->index('occurred_at', 'http_operation_logs_retention');
        });
    }

    /**
     * 回退時刪除紀錄表；只含觀測資料，不影響業務資料。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('http_operation_logs');
    }
};
