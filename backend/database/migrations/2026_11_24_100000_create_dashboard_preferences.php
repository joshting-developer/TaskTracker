<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TT-186：首頁小工具偏好以工作區成員為單位, 換工作區或重新登入都能還原 */
return new class extends Migration
{
    /** 建立首頁偏好資料表
     * @return void
     */
    public function up(): void
    {
        Schema::create('dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->unique()->constrained('workspace_members')->cascadeOnDelete();
            $table->json('widgets');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps(6);
        });
    }

    /** 移除首頁偏好資料表
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_preferences');
    }
};
