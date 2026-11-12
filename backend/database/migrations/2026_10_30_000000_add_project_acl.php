<?php

use App\Database\ProjectAclUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 補入專案ACL與明確成員資格，保留既有業務資料。
     *
     * @return void
     */
    public function up(): void
    {
        ProjectAclUpgrade::run();
    }

    /**
     * 不移除授權／成員歷史或猜測舊角色；回退需還原升級前備份。
     *
     * @return void
     */
    public function down(): void
    {
        // 不推測還原授權或移除成員歷史, 回舊版應還原升級前備份
    }
};
