<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TT-176：來源稽核加外部參照與寫入後revision，供追溯run及回音辨識；不保存內容或秘密。 */
return new class extends Migration
{
    /**
     * 新增可空欄位與依資源查最新寫入的索引；既有列保持null。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('integration_audits', function (Blueprint $table) {
            $table->string('client_reference', 120)->nullable();
            $table->unsignedInteger('resource_revision')->nullable();
            $table->index(['workspace_id', 'resource_id', 'resource_revision'], 'integration_audit_resource');
        });
    }

    /**
     * 回退時移除新增欄位與索引。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('integration_audits', function (Blueprint $table) {
            $table->dropIndex('integration_audit_resource');
            $table->dropColumn(['client_reference', 'resource_revision']);
        });
    }
};
