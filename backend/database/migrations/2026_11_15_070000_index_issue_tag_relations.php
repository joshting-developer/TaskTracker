<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 支援 Eloquent 依卡片 ID 預載及篩選標籤, 避免掃描整份關聯索引 */
return new class extends Migration
{
    /** 補上關聯查詢使用的左前綴, 不修改既有關聯或外鍵
     * @return void
     */
    public function up(): void
    {
        Schema::table('issue_tags', function (Blueprint $table): void {
            $table->index(['issue_id', 'tag_id'], 'issue_tags_issue');
        });
    }

    /** 只移除本次索引, 保留標籤與關聯資料
     * @return void
     */
    public function down(): void
    {
        Schema::table('issue_tags', function (Blueprint $table): void {
            $table->dropIndex('issue_tags_issue');
        });
    }
};
