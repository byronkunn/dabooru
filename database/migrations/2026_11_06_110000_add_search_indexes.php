<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Column indexes that make LIKE 'term%' searches cheap enough on the
        // small tables. A real full-text engine (MySQL FULLTEXT, Meilisearch or
        // Scout) is the upgrade path once the library gets large.
        Schema::table('posts', function (Blueprint $table) {
            $table->index('title');
        });

        Schema::table('comics', function (Blueprint $table) {
            $table->index('title');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropIndex(['title']));
        Schema::table('comics', fn (Blueprint $table) => $table->dropIndex(['title']));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['name']));
    }
};
