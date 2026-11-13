<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TT-309：看板加上簡述, 說明這個看板收哪類卡片；舊看板預設空字串。 */
return new class extends Migration
{
    /**
     * 新增最多300字的簡述欄位。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table): void {
            $table->string('description', 300)->default('')->after('name');
        });
    }

    /**
     * 移除簡述欄位。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
