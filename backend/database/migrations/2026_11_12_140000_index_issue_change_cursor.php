<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 同租戶／Project的增量keyset覆蓋索引，不改既有內容／時間。 */
return new class extends Migration
{
    /**
     * 建立同Workspace／Project依updated_at與id做keyset增量查詢的複合索引。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->index(['workspace_id', 'project_id', 'updated_at', 'id'], 'issues_change_cursor'));
    }

    /**
     * 僅移除issues_change_cursor索引，卡片內容與時間不變。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->dropIndex('issues_change_cursor'));
    }
};
