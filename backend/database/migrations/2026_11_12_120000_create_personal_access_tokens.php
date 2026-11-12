<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sanctum雜湊Token與固定Workspace；保留撤銷／到期資料，不保存原文。 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->string('tokenable_id', 36);
            $table->foreign('tokenable_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('token', 64)->unique();
            $table->text('abilities');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('issuance_request_id');
            $table->timestamps();
            $table->unique(['tokenable_id', 'issuance_request_id'], 'pat_issuance_unique');
            $table->index(['tokenable_id', 'workspace_id', 'id'], 'pat_owner_workspace_page');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
