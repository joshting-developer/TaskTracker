<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 只為新註冊加入驗證要求, 既有帳號保留原驗證時間與登入資格
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_verification_required')->default(false);
        });
    }

    /** 移除新帳號驗證要求欄位
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verification_required');
        });
    }
};
