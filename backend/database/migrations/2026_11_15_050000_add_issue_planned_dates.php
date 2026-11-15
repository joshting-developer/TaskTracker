<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->dropColumn(['start_date', 'end_date']));
    }
};
