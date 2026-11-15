<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 建立本次可回退的獨立欄位或資料表
     * @return void
     */
    public function up(): void
    {
        Schema::create('project_tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id');
            $table->string('name', 40);
            $table->string('name_key', 80);
            $table->string('color', 7);
            $table->string('description', 300)->default('');
            $table->unsignedInteger('revision')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['workspace_id', 'project_id', 'id']);
            $table->unique(['project_id', 'name_key']);
            $table->index(['workspace_id', 'project_id', 'deleted_at', 'name', 'id'], 'project_tags_page');
            $table->foreign(['workspace_id', 'project_id'])->references(['workspace_id', 'id'])->on('projects')->cascadeOnDelete();
        });
        Schema::create('issue_tags', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('issue_id');
            $table->unsignedBigInteger('tag_id');
            $table->primary(['workspace_id', 'project_id', 'issue_id', 'tag_id']);
            $table->index(['workspace_id', 'project_id', 'tag_id', 'issue_id'], 'issue_tags_filter');
            $table->foreign(['workspace_id', 'project_id', 'issue_id'])->references(['workspace_id', 'project_id', 'id'])->on('issues')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'tag_id'])->references(['workspace_id', 'project_id', 'id'])->on('project_tags')->cascadeOnDelete();
        });
    }

    /** 依外鍵順序回退本次新增結構
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_tags');
        Schema::dropIfExists('project_tags');
    }
};
