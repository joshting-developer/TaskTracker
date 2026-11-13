<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Worker中斷恢復使用獨立租約索引, 不改既有郵件資料 */
return new class extends Migration
{
    /** 索引支援status／started_at穩定有界查詢
     * @return void
     */
    public function up(): void
    {
        Schema::table('mail_deliveries', fn (Blueprint $table) => $table->index(['status', 'started_at', 'id'], 'mail_deliveries_sending_idx'));
    }

    /** 回退只移除索引, 保留寄送紀錄
     * @return void
     */
    public function down(): void
    {
        Schema::table('mail_deliveries', fn (Blueprint $table) => $table->dropIndex('mail_deliveries_sending_idx'));
    }
};
