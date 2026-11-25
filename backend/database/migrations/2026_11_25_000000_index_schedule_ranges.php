<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 單端日期視為單日, 以範圍索引支援重疊範圍與未排程計數。 */
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->date('schedule_start')->nullable()->storedAs('COALESCE(start_date, end_date)');
            $table->date('schedule_end')->nullable()->storedAs('COALESCE(end_date, start_date)');
            $table->index(['workspace_id', 'project_id', 'state_id', 'schedule_end', 'schedule_start', 'assignee_id'], 'issues_schedule_state_range');
            $table->index(['workspace_id', 'project_id', 'schedule_end', 'schedule_start', 'state_id', 'assignee_id'], 'issues_schedule_range');
        });
    }

    /** 移除衍生欄位前先移除索引。 */
    public function down(): void
    {
        // MySQL 可能以新複合索引取代隱含的外鍵索引, 回退前先補回。
        if (! Schema::hasIndex('issues', 'issues_state_id_fk')) {
            Schema::table('issues', fn (Blueprint $table) => $table->index(['workspace_id', 'project_id', 'state_id'], 'issues_state_id_fk'));
        }
        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex('issues_schedule_state_range');
            $table->dropIndex('issues_schedule_range');
            $table->dropColumn(['schedule_start', 'schedule_end']);
        });
    }
};
