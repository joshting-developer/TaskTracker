<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 新增原稿欄位；舊title／note不改，先前回退保留的原稿可恢復。
     * @return void
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->text('content')->nullable();
        });
        DB::table('todos')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $legacy = json_decode($row->legacy_attributes ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                if (! array_key_exists('content', $legacy)) {
                    continue;
                }
                $content = $legacy['content'];
                unset($legacy['content']);
                DB::table('todos')->where('id', $row->id)->update([
                    'content' => $content,
                    'legacy_attributes' => json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    /** 回退時將新格式原稿保留到歷史相容欄，避免移除欄位而遺失內容。
     * @return void
     */
    public function down(): void
    {
        DB::table('todos')->whereNotNull('content')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $legacy = json_decode($row->legacy_attributes ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                $legacy['content'] = $row->content;
                DB::table('todos')->where('id', $row->id)->update([
                    'legacy_attributes' => json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }
        });
        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
