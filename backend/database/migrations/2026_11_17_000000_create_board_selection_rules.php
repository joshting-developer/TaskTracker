<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 建立可由 SQL 交集的規則與明確排除, 舊看板沒有規則列
     * @return void
     */
    public function up(): void
    {
        Schema::create('board_rules', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('board_id');
            $table->string('rule_key', 110);
            $table->string('kind', 10);
            $table->string('value', 100);
            $table->primary(['board_id', 'rule_key']);
            $table->index(['board_id', 'kind', 'value']);
            $table->foreign(['workspace_id', 'project_id', 'board_id'])->references(['workspace_id', 'project_id', 'id'])->on('boards')->cascadeOnDelete();
        });
        Schema::create('board_exclusions', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('board_id');
            $table->unsignedBigInteger('issue_id');
            $table->primary(['board_id', 'issue_id']);
            $table->foreign(['workspace_id', 'project_id', 'board_id'])->references(['workspace_id', 'project_id', 'id'])->on('boards')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'issue_id'])->references(['workspace_id', 'project_id', 'id'])->on('issues')->cascadeOnDelete();
        });
    }

    /** 只回退本次新增的規則與排除表
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('board_exclusions');
        Schema::dropIfExists('board_rules');
    }
};
