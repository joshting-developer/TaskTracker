<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 建立新帳號信箱驗證結構, 不改寫既有帳號驗證時間
     * @return void
     */
    public function up(): void
    {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->uuid('workspace_id');
            $table->string('code_hash');
            $table->string('token_hash', 64)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at');
            $table->timestamp('consumed_at')->nullable();
        });
        Schema::table('mail_deliveries', function (Blueprint $table) {
            $table->uuid('verification_id')->nullable()->index();
        });
    }

    /** 移除驗證結構, 不回退既有帳號驗證時間
     * @return void
     */
    public function down(): void
    {
        Schema::table('mail_deliveries', function (Blueprint $table) {
            $table->dropColumn('verification_id');
        });
        Schema::dropIfExists('email_verifications');
    }
};
