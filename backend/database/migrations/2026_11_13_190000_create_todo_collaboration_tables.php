<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 建立待辦留言、經手資格與目前交接路徑, 外鍵維持工作區隔離
     * @return void
     */
    public function up(): void
    {
        Schema::create('todo_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('todo_id');
            $table->unsignedBigInteger('member_id');
            $table->primary(['todo_id', 'member_id']);
            $table->index(['workspace_id', 'member_id', 'todo_id'], 'todo_participants_reader');
            $table->foreign(['workspace_id', 'todo_id'])->references(['workspace_id', 'id'])->on('todos')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'member_id'])->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('todo_assignment_paths', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('todo_id');
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('member_id');
            $table->primary(['todo_id', 'position']);
            $table->foreign(['workspace_id', 'todo_id'])->references(['workspace_id', 'id'])->on('todos')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'member_id'])->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('todo_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('todo_id');
            $table->unsignedBigInteger('author_id');
            $table->string('author_name');
            $table->text('content');
            $table->dateTime('created_at', 6);
            $table->index(['workspace_id', 'todo_id', 'created_at', 'id'], 'todo_comments_page');
            $table->foreign(['workspace_id', 'todo_id'])->references(['workspace_id', 'id'])->on('todos')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'author_id'])->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('todo_handoffs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('todo_id');
            $table->unsignedBigInteger('from_id');
            $table->unsignedBigInteger('to_id');
            $table->string('from_name');
            $table->string('to_name');
            $table->string('kind', 20);
            $table->dateTime('created_at', 6);
            $table->index(['workspace_id', 'todo_id', 'created_at', 'id'], 'todo_handoffs_page');
            $table->foreign(['workspace_id', 'todo_id'])->references(['workspace_id', 'id'])->on('todos')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'from_id'])->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
            $table->foreign(['workspace_id', 'to_id'])->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        DB::table('todos')->orderBy('id')->chunkById(100, function ($todos) {
            foreach ($todos as $todo) {
                foreach (array_unique([$todo->created_by_id, $todo->initial_assignee_id, $todo->assignee_id]) as $member) {
                    DB::table('todo_participants')->insert(['workspace_id' => $todo->workspace_id, 'todo_id' => $todo->id, 'member_id' => $member]);
                }
                $path = array_values(array_unique([$todo->created_by_id, $todo->assignee_id]));
                foreach ($path as $position => $member) {
                    DB::table('todo_assignment_paths')->insert(['workspace_id' => $todo->workspace_id, 'todo_id' => $todo->id, 'position' => $position, 'member_id' => $member]);
                }
            }
        });
    }

    /** 依外鍵順序移除本卡結構, 保留待辦本身
     * @return void
     */
    public function down(): void
    {
        foreach (['todo_handoffs', 'todo_comments', 'todo_assignment_paths', 'todo_participants'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
