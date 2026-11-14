<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 保留既有登入, 只在密碼重設後遞增Session版本並辨識當次重設信件
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('session_epoch')->default(0));
        Schema::table('mail_deliveries', fn (Blueprint $table) => $table->char('password_reset_hash', 64)->nullable());
    }

    /** 移除本次新增欄位, 不刪帳號、信件或重設資料
     * @return void
     */
    public function down(): void
    {
        Schema::table('mail_deliveries', fn (Blueprint $table) => $table->dropColumn('password_reset_hash'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('session_epoch'));
    }
};
