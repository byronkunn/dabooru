<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            $table->foreignId('cover_media_id')
                ->nullable()
                ->after('slug')
                ->constrained('media')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_media_id');
        });
    }
};
