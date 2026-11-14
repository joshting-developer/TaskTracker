<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 保存每個帳號的建立請求, 空間刪除後保留不可重建的憑證
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('workspace_creation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('request_id');
            $table->char('signature', 64);
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->unique(['user_id', 'request_id'], 'workspace_creation_actor_request_unique');
        });
    }

    /**
     * 只移除建立請求紀錄, 保留所有已建立工作空間與成員
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_creation_requests');
    }
};
