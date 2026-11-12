<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** TT-171：Project範圍的外部識別對照；requestId仍只負責單次冪等，不作長期資源識別。 */
return new class extends Migration
{
    /**
     * 新增對照表；同Project內(source, externalId)唯一，一張卡每個來源只綁一個外部識別。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('issue_external_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $source = $table->string('source', 40);
            $external = $table->string('external_id', 200);
            if (DB::getDriverName() === 'mysql') {
                // 外部識別區分大小寫；預設ai_ci會把ABC與abc視為同一鍵。
                $source->collation('utf8mb4_bin');
                $external->collation('utf8mb4_bin');
            }
            $table->dateTime('created_at', 6);
            $table->unique(['project_id', 'source', 'external_id'], 'issue_external_ref_key');
            $table->unique(['issue_id', 'source'], 'issue_external_ref_issue_source');
            $table->index(['workspace_id', 'project_id'], 'issue_external_ref_scope');
        });
    }

    /**
     * 回退時只移除此新增表，不影響卡片。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_external_refs');
    }
};
