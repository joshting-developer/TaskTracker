<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

    public function down(): void
    {
        Schema::dropIfExists('records');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['legacy_salt', 'legacy_hash']));
    }
};
