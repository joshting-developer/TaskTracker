<?php

use App\Database\IssueRelationsUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 將舊前置依賴轉為雙向關聯，保留既有卡片歷史。
     *
     * @return void
     */
    public function up(): void
    {
        IssueRelationsUpgrade::run();
    }

    /**
     * 不推測還原已失去方向的依賴；回舊版須使用升級前備份。
     *
     * @return void
     */
    public function down(): void
    {
        // 關聯不再具有依賴方向, 不反向推測；還原舊版本需使用升級前備份
    }
};
