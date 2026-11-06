<?php

use App\Services\ProjectStatusTypesUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** 補齊必要狀態，保留Issue外鍵、狀態值、工時與歷史。
     * @return void
     */
    public function up(): void
    {
        ProjectStatusTypesUpgrade::run();
    }

    /** 保留補入的狀態，避免回退時刪除已被使用的狀態。
     * @return void
     */
    public function down(): void
    {
        // 資料升級不自動逆轉，舊程式仍可讀取這些狀態。
    }
};
