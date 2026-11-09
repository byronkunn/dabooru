<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_collections', function (Blueprint $table) {
            $table->boolean('is_public')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('saved_collections', fn (Blueprint $table) => $table->dropColumn('is_public'));
    }
};
