<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('rating')->default('sfw');       // the series' declared rating
            $table->string('status')->default('ongoing');   // ongoing | completed | hiatus
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamp('last_chapter_at')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['rating']);
        });

        Schema::create('comic_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comic_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title')->nullable();
            $table->string('rating')->default('sfw');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['comic_id', 'number']);
        });

        Schema::create('comic_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comic_chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->unique(['comic_chapter_id', 'position']);
        });

        // comic_chapter_id now exists on posts, so the FK can be added
        Schema::table('posts', function (Blueprint $table) {
            $table->foreign('comic_chapter_id')->references('id')->on('comic_chapters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['comic_chapter_id']);
        });
        Schema::dropIfExists('comic_pages');
        Schema::dropIfExists('comic_chapters');
        Schema::dropIfExists('comics');
    }
};
