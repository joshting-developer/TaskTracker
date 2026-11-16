<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 為工作區新增顯示時區, 不改寫 UTC 紀錄或工時工作日期 */
return new class extends Migration
{
    /** 新舊工作區皆預設 UTC, 設定值為 IANA 時區名稱
     * @return void
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC');
        });
        // 舊 records 回匯暫存於 legacy_attributes, 只恢復有效的顯示偏好
        DB::table('workspaces')->select(['id', 'legacy_attributes'])->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $legacy = json_decode($row->legacy_attributes ?? '{}', true);
                $zone = is_array($legacy) ? ($legacy['timeZone'] ?? null) : null;
                if (is_string($zone) && $zone !== 'Factory' && in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
                    DB::table('workspaces')->where('id', $row->id)->update(['timezone' => $zone]);
                }
            }
        });
    }

    /** 移除顯示偏好, 不更動任何歷史時間
     * @return void
     */
    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('timezone'));
    }
};
