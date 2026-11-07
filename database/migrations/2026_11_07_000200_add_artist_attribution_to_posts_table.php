<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_original')->default(true)->after('rating');
            $table->string('artist_name', 120)->nullable()->after('is_original');
            $table->string('source_url', 500)->nullable()->after('artist_name');

            $table->index('artist_name');
            $table->index('is_original');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['artist_name']);
            $table->dropIndex(['is_original']);
            $table->dropColumn(['is_original', 'artist_name', 'source_url']);
        });
    }
};
