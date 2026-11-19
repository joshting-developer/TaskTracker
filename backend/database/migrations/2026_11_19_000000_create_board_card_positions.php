<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** TT-428：看板內手動排序, 範圍是看板＋卡片；Default 看板沒有資料列, board_id 留空、board_key 寫 0
     * @return void
     */
    public function up(): void
    {
        Schema::create('board_card_positions', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('board_id')->nullable();
            // MySQL 不允許串聯刪除的外鍵欄位再被生成欄位使用, board_key 由程式寫入（Default=0）
            $table->unsignedBigInteger('board_key');
            $table->unsignedBigInteger('issue_id');
            $table->bigInteger('position');
            // 每次寫入加一；看板版本取計數與加總, 只增不減, 互換兩張卡也看得出變更
            $table->unsignedInteger('revision')->default(1);
            $table->primary(['project_id', 'board_key', 'issue_id']);
            $table->foreign(['workspace_id', 'project_id', 'board_id'])->references(['workspace_id', 'project_id', 'id'])->on('boards')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'project_id', 'issue_id'])->references(['workspace_id', 'project_id', 'id'])->on('issues')->cascadeOnDelete();
        });
    }

    /** 只回退本次新增的排序表, 卡片與看板不受影響
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('board_card_positions');
    }
};
