<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 本人全域設定的重試結果不依賴任何Workspace
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('account_profile_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_id', 100);
            $table->char('signature', 64);
            $table->json('result');
            $table->unique(['user_id', 'request_id'], 'account_profile_actor_request_unique');
        });
    }

    /**
     * 移除本次重試表, 不回滾已儲存的帳號資料
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('account_profile_requests');
    }
};
