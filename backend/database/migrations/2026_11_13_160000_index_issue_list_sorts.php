<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 只補MySQL量測證實有益的常用倒序；不取代增量keyset索引 */
return new class extends Migration
{
    /**
     * 兩種混合方向符合列表的穩定卡號／公開ID排序
     *
     * @return void
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX issues_list_updated_desc ON issues (workspace_id, project_id, updated_at DESC, identifier ASC, public_id ASC)');
        DB::statement('CREATE INDEX issues_list_created_desc ON issues (workspace_id, project_id, created_at DESC, updated_at DESC, identifier ASC, public_id ASC)');
    }

    /**
     * 只移除本次新增索引, 不刪資料或既有索引
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex('issues_list_created_desc');
            $table->dropIndex('issues_list_updated_desc');
        });
    }
};
