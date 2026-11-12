<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 建立具名交易守衛表並初始化accounts鎖列。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('transaction_guards', fn (Blueprint $table) => $table->string('name', 40)->primary());
        DB::table('transaction_guards')->insert(['name' => 'accounts']);
    }

    /**
     * 移除交易守衛表與其初始化鎖列。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_guards');
    }
};
