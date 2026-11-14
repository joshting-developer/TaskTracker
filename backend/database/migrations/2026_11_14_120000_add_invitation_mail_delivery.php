<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 邀請寄信的單筆指標與有界待接受查詢索引, 不變更既有成員 */
return new class extends Migration
{
    /**
     * 建立邀請與寄送事件的一對一關聯及查詢索引
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('mail_deliveries', function (Blueprint $table) {
            $table->foreignId('invitation_id')->nullable()->constrained('workspace_invitations')->nullOnDelete();
            $table->unique('invitation_id', 'mail_deliveries_invitation_unique');
        });
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->index(['workspace_id', 'consumed_at', 'expires_at', 'id'], 'workspace_invites_pending');
            $table->index(['workspace_id', 'email'], 'workspace_invites_email');
        });
    }

    /**
     * 僅回退本次指標與索引, 不移除邀請或信件資料
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('mail_deliveries', function (Blueprint $table) {
            $table->dropUnique('mail_deliveries_invitation_unique');
            $table->dropConstrainedForeignId('invitation_id');
        });
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->dropIndex('workspace_invites_pending');
            $table->dropIndex('workspace_invites_email');
        });
    }
};
