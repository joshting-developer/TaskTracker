<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 保存受邀者決定與可重驗的邀請者, 舊寄信紀錄只補已知來源
     * @return void
     */
    public function up(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->foreignUuid('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('declined_at', 6)->nullable();
        });
        DB::table('workspace_invitations')->update(['invited_by_id' => DB::raw('(SELECT user_id FROM mail_deliveries WHERE mail_deliveries.invitation_id = workspace_invitations.id ORDER BY id LIMIT 1)')]);
    }

    /** 移除本次新增欄位, 不刪既有邀請或帳號
     * @return void
     */
    public function down(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->dropForeign(['invited_by_id']);
            $table->dropColumn(['invited_by_id', 'declined_at']);
        });
    }
};
