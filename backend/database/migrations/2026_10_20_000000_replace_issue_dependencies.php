<?php

use App\Services\IssueRelationsUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IssueRelationsUpgrade::run();
    }

    public function down(): void
    {
        // 關聯不再具有依賴方向, 不反向推測；還原舊版本需使用升級前備份
    }
};
