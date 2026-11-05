<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 新增獨立設定版本，不改既有姓名、角色與密碼。
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('profile_revision')->default(0);
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedInteger('settings_revision')->default(0);
        });
        Schema::table('workspace_members', function (Blueprint $table) {
            $table->unsignedInteger('role_revision')->default(0);
        });
    }

    /** 移除版本欄；既有設定值保持不變。
     * @return void
     */
    public function down(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            $table->dropColumn('role_revision');
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('settings_revision');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_revision');
        });
    }
};
