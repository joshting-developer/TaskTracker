<?php

use App\Database\ProjectAclUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ProjectAclUpgrade::run();
    }

    public function down(): void
    {
        // 不推測還原授權或移除成員歷史, 回舊版應還原升級前備份
    }
};
