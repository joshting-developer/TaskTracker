<?php

use App\Services\RelationalDataMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 建立具明確欄位、外鍵與索引的業務表並回填舊資料。
     * @return void
     */
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->unique('public_id');
            $table->string('name');
            $table->timestamp('created_at')->nullable();
            $table->json('legacy_attributes')->nullable();
        });
        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'user_id'], 'workspace_members_public_unique');
            $table->unique(['workspace_id', 'id'], 'workspace_members_scope_unique');
            $table->string('role')->default('user');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->json('legacy_attributes')->nullable();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'projects_public_unique');
            $table->unique(['workspace_id', 'id'], 'projects_scope_unique');
            $table->string('name');
            $table->string('code');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->text('description')->default('');
            $table->string('color')->default('#8d83ed');
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('legacy_attributes')->nullable();
            $table->unique(['workspace_id', 'code']);
            $table->foreign(['workspace_id', 'lead_id'], 'projects_lead_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unsignedBigInteger('project_id');
            $table->string('code', 100);
            $table->string('label', 30);
            $table->string('category', 20);
            $table->string('color', 7);
            $table->unsignedInteger('position');
            $table->unique(['project_id', 'code']);
            $table->unique(['workspace_id', 'project_id', 'id']);
            $table->foreign(['workspace_id', 'project_id'])->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
        });
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'issues_public_unique');
            $table->unique(['workspace_id', 'id'], 'issues_scope_unique');
            $table->unsignedBigInteger('project_id');
            $table->string('identifier');
            $table->unsignedBigInteger('storage_position')->default(0);
            $table->index(['workspace_id', 'storage_position']);
            $table->string('title');
            $table->text('description')->default('');
            $table->unsignedBigInteger('state_id');
            $table->string('priority')->default('normal');
            $table->string('type')->default('task');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('epic_id')->nullable();
            $table->unsignedInteger('estimate_minutes')->default(0);
            $table->unsignedBigInteger('parent_issue_id')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('legacy_attributes')->nullable();
            $table->unique(['workspace_id', 'project_id', 'id'], 'issues_project_unique');
            $table->unique(['workspace_id', 'project_id', 'identifier']);
            $table->index(['workspace_id', 'assignee_id', 'state_id']);
            $table->foreign(['workspace_id', 'project_id'], 'issues_project_id_fk')->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'state_id'], 'issues_state_id_fk')->references(['workspace_id', 'project_id', 'id'])->on('project_statuses')->restrictOnDelete();
            $table->foreign(['workspace_id', 'assignee_id'], 'issues_assignee_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'epic_id'], 'issues_epic_id_fk')->references(['workspace_id', 'project_id', 'id'])->on('issues')->restrictOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'parent_issue_id'], 'issues_parent_issue_id_fk')->references(['workspace_id', 'project_id', 'id'])->on('issues')->restrictOnDelete();
        });
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'boards_public_unique');
            $table->unique(['workspace_id', 'id'], 'boards_scope_unique');
            $table->unsignedBigInteger('project_id');
            $table->string('name');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('legacy_attributes')->nullable();
            $table->unique(['workspace_id', 'project_id', 'id'], 'boards_project_unique');
            $table->foreign(['workspace_id', 'project_id'], 'boards_project_id_fk')->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['workspace_id', 'created_by_id'], 'boards_created_by_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'project_members_public_unique');
            $table->unique(['workspace_id', 'id'], 'project_members_scope_unique');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('member_id');
            $table->string('role_id');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('legacy_attributes')->nullable();
            $table->unique(['workspace_id', 'project_id', 'id'], 'project_members_project_unique');
            $table->unique(['project_id', 'member_id']);
            $table->foreign(['workspace_id', 'project_id'], 'project_members_project_id_fk')->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['workspace_id', 'member_id'], 'project_members_member_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('project_access_controls', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'project_access_controls_public_unique');
            $table->unique(['workspace_id', 'id'], 'project_access_controls_scope_unique');
            $table->unsignedBigInteger('project_id');
            $table->unsignedInteger('revision')->default(0);
            $table->json('legacy_attributes')->nullable();
            $table->unique(['workspace_id', 'project_id', 'id'], 'project_access_controls_project_unique');
            $table->unique('project_id');
            $table->foreign(['workspace_id', 'project_id'], 'project_access_controls_project_id_fk')->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
        });
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'time_entries_public_unique');
            $table->unique(['workspace_id', 'id'], 'time_entries_scope_unique');
            $table->unsignedBigInteger('issue_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('member_id');
            $table->timestamp('created_at')->nullable();
            $table->date('work_date');
            $table->unsignedInteger('minutes');
            $table->text('note')->default('');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('legacy_attributes')->nullable();
            $table->unique(['workspace_id', 'project_id', 'id'], 'time_entries_project_unique');
            $table->index(['workspace_id', 'member_id', 'work_date']);
            $table->foreign(['workspace_id', 'project_id', 'issue_id'], 'time_entries_issue_id_fk')->references(['workspace_id', 'project_id', 'id'])->on('issues')->restrictOnDelete();
            $table->foreign(['workspace_id', 'project_id'], 'time_entries_project_id_fk')->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['workspace_id', 'member_id'], 'time_entries_member_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('issue_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'issue_notifications_public_unique');
            $table->unique(['workspace_id', 'id'], 'issue_notifications_scope_unique');
            $table->unsignedBigInteger('issue_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('recipient_id');
            $table->string('email');
            $table->string('subject');
            $table->text('body')->default('');
            $table->string('status')->default('preview');
            $table->timestamp('created_at')->nullable();
            $table->json('legacy_attributes')->nullable();
            $table->unique(['workspace_id', 'project_id', 'id'], 'issue_notifications_project_unique');
            $table->foreign(['workspace_id', 'project_id', 'issue_id'], 'issue_notifications_issue_id_fk')->references(['workspace_id', 'project_id', 'id'])->on('issues')->restrictOnDelete();
            $table->foreign(['workspace_id', 'project_id'], 'issue_notifications_project_id_fk')->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
            $table->foreign(['workspace_id', 'recipient_id'], 'issue_notifications_recipient_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'todos_public_unique');
            $table->unique(['workspace_id', 'id'], 'todos_scope_unique');
            $table->string('title');
            $table->text('note')->default('');
            $table->unsignedBigInteger('created_by_id');
            $table->string('creator_name');
            $table->unsignedBigInteger('assignee_id');
            $table->unsignedBigInteger('initial_assignee_id');
            $table->string('assignee_name');
            $table->string('state')->default('open');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('converted_issue_id')->nullable();
            $table->unsignedBigInteger('converted_project_id')->nullable();
            $table->json('legacy_attributes')->nullable();
            $table->index(['workspace_id', 'assignee_id', 'state']);
            $table->foreign(['workspace_id', 'created_by_id'], 'todos_created_by_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
            $table->foreign(['workspace_id', 'assignee_id'], 'todos_assignee_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
            $table->foreign(['workspace_id', 'initial_assignee_id'], 'todos_initial_assignee_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
            $table->foreign(['workspace_id', 'converted_project_id', 'converted_issue_id'], 'todos_converted_issue_id_fk')->references(['workspace_id', 'project_id', 'id'])->on('issues')->restrictOnDelete();
            $table->foreign(['workspace_id', 'converted_project_id'], 'todos_converted_project_id_fk')->references(['workspace_id', 'id'])->on('projects')->restrictOnDelete();
        });
        Schema::create('todo_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'todo_notifications_public_unique');
            $table->unique(['workspace_id', 'id'], 'todo_notifications_scope_unique');
            $table->unsignedBigInteger('todo_id');
            $table->unsignedBigInteger('recipient_id');
            $table->string('event');
            $table->string('actor');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('legacy_attributes')->nullable();
            $table->foreign(['workspace_id', 'todo_id'], 'todo_notifications_todo_id_fk')->references(['workspace_id', 'id'])->on('todos')->restrictOnDelete();
            $table->foreign(['workspace_id', 'recipient_id'], 'todo_notifications_recipient_id_fk')->references(['workspace_id', 'id'])->on('workspace_members')->restrictOnDelete();
        });
        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200)->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'workspace_invitations_public_unique');
            $table->unique(['workspace_id', 'id'], 'workspace_invitations_scope_unique');
            $table->string('email');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->json('legacy_attributes')->nullable();
        });
        Schema::create('demo_seed_states', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200)->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'demo_seed_states_public_unique');
            $table->unique(['workspace_id', 'id'], 'demo_seed_states_scope_unique');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('seeded_at')->nullable();
            $table->timestamp('upgraded_at')->nullable();
            $table->json('legacy_attributes')->nullable();
        });
        Schema::create('workspace_upgrades', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'workspace_upgrades_public_unique');
            $table->unique(['workspace_id', 'id'], 'workspace_upgrades_scope_unique');
            $table->timestamp('applied_at')->nullable();
            $table->string('actor');
            $table->text('note')->default('');
            $table->json('role_changes')->nullable();
            $table->json('legacy_attributes')->nullable();
        });
        Schema::create('retired_saved_views', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'retired_saved_views_public_unique');
            $table->unique(['workspace_id', 'id'], 'retired_saved_views_scope_unique');
            $table->json('legacy_attributes')->nullable();
        });
        Schema::create('operation_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'operation_receipts_public_unique');
            $table->unique(['workspace_id', 'id'], 'operation_receipts_scope_unique');
            $table->string('signature');
            $table->json('result')->nullable();
            $table->json('legacy_attributes')->nullable();
        });
        Schema::create('todo_operation_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 200);
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->unique(['workspace_id', 'public_id'], 'todo_operation_receipts_public_unique');
            $table->unique(['workspace_id', 'id'], 'todo_operation_receipts_scope_unique');
            $table->string('signature');
            $table->json('result')->nullable();
            $table->json('legacy_attributes')->nullable();
        });
        Schema::create('issue_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->string('public_id', 200);
            $table->text('content');
            $table->string('author');
            $table->timestamp('created_at')->nullable();
            $table->unsignedInteger('position');
            $table->unique(['issue_id', 'public_id']);
        });
        Schema::create('issue_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('issue_id');
            $table->unsignedBigInteger('related_issue_id');
            $table->unsignedInteger('position');
            $table->unique(['issue_id', 'related_issue_id']);
            $table->foreign(['workspace_id', 'project_id', 'issue_id'])->references(['workspace_id', 'project_id', 'id'])->on('issues')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'related_issue_id'])->references(['workspace_id', 'project_id', 'id'])->on('issues')->restrictOnDelete();
        });
        Schema::create('board_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('board_id');
            $table->unsignedBigInteger('issue_id');
            $table->unsignedInteger('position');
            $table->unique(['board_id', 'issue_id']);
            $table->foreign(['workspace_id', 'project_id', 'board_id'])->references(['workspace_id', 'project_id', 'id'])->on('boards')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'issue_id'])->references(['workspace_id', 'project_id', 'id'])->on('issues')->restrictOnDelete();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->restrictOnDelete();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('stream', 40);
            $table->unsignedInteger('position');
            $table->json('event_data');
            $table->unique(['entity_type', 'entity_id', 'stream', 'position']);
        });
        RelationalDataMigration::import();
    }

    /** 匯出最新資料後回退，避免回退後只剩升級前快照。
     * @return void
     */
    public function down(): void
    {
        RelationalDataMigration::export();
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('board_issues');
        Schema::dropIfExists('issue_relations');
        Schema::dropIfExists('issue_comments');
        Schema::dropIfExists('todo_operation_receipts');
        Schema::dropIfExists('operation_receipts');
        Schema::dropIfExists('retired_saved_views');
        Schema::dropIfExists('workspace_upgrades');
        Schema::dropIfExists('demo_seed_states');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('todo_notifications');
        Schema::dropIfExists('todos');
        Schema::dropIfExists('issue_notifications');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('project_access_controls');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('boards');
        // SQLite在DROP時仍檢查自我外鍵；最新內容已匯出，再由子到父移除。
        DB::table('issues')->whereNotNull('parent_issue_id')->delete();
        DB::table('issues')->where('type', '!=', 'epic')->delete();
        Schema::dropIfExists('issues');
        Schema::dropIfExists('project_statuses');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
