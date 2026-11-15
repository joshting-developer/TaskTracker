<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 建立本次可回退的獨立欄位或資料表
     * @return void
     */
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
    }

    /** 依外鍵順序回退本次新增結構
     * @return void
     */
    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->dropColumn(['start_date', 'end_date']));
    }
};
