<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立舊版records存放表，並加入相容Node密碼雜湊的帳號欄位。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->id('sequence');
            $table->string('kind', 100);
            $table->string('id', 200);
            $table->text('payload');
            $table->unique(['kind', 'id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('legacy_salt')->nullable();
            $table->string('legacy_hash')->nullable();
        });
    }

    /**
     * 移除舊records表及舊密碼雜湊欄位；不嘗試重建被移除內容。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('records');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['legacy_salt', 'legacy_hash']));
    }
};
