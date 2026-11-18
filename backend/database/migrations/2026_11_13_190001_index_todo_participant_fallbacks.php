<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 支援既有／seed待辦的建立者及初始經手索引, 避免回退查詢掃整個工作區
     * @return void
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->index(['workspace_id', 'created_by_id', 'state', 'id'], 'todos_creator_scope');
            $table->index(['workspace_id', 'initial_assignee_id', 'state', 'id'], 'todos_initial_handler_scope');
        });
    }

    /** 只移除本次索引
     * @return void
     */
    public function down(): void
    {
        // InnoDB 可能在新增較長索引時移除原本的外鍵索引, 回退前先補回。
        foreach (['created_by_id' => 'todos_created_by_id_fk', 'initial_assignee_id' => 'todos_initial_assignee_id_fk'] as $column => $index) {
            if (! Schema::hasIndex('todos', $index)) {
                Schema::table('todos', fn (Blueprint $table) => $table->index(['workspace_id', $column], $index));
            }
        }
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex('todos_creator_scope');
            $table->dropIndex('todos_initial_handler_scope');
        });
    }
};
