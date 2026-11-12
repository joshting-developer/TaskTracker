<?php

use App\Database\IssueTypesUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 將舊feature類型升級為task，保留原有內容與歷史。
     *
     * @return void
     */
    public function up(): void
    {
        IssueTypesUpgrade::run();
    }

    /**
     * 不猜測哪些Task原為Feature；回舊版須使用升級前備份。
     *
     * @return void
     */
    public function down(): void
    {
        // Task 不再區分原 Feature, 不猜測逆轉；回舊版請還原升級前備份
    }
};
