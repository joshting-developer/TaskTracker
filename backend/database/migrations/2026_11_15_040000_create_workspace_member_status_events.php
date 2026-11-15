<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 保存資格變更而非刪除歷史成員
     * @return void
     */
    public function up(): void
    {
        Schema::create('workspace_member_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('member_id');
            $table->foreign(['workspace_id', 'member_id'])->references(['workspace_id', 'id'])->on('workspace_members')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('revision');
            $table->dateTime('occurred_at', 6);
            $table->unique(['member_id', 'revision']);
        });
    }

    /** 只移除本次事件表
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_member_status_events');
    }
};
