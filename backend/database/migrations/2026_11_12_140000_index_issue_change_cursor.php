<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 同租戶／Project的增量keyset覆蓋索引，不改既有內容／時間。 */
return new class extends Migration
{
    /** @return void 建立複合seek索引。 */
    public function up(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->index(['workspace_id', 'project_id', 'updated_at', 'id'], 'issues_change_cursor'));
    }

    /** @return void 僅移除本索引。 */
    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->dropIndex('issues_change_cursor'));
    }
};
