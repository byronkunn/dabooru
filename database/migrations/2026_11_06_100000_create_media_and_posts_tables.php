<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');                       // original
            $table->string('thumb_path')->nullable();     // grid / feed derivative
            $table->string('kind');                       // image | gif | video
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status')->default('ready');   // pending | processing | ready | failed
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                       // image | gif | video | album | comic
            $table->string('rating')->default('sfw');     // sfw | suggestive | explicit
            $table->string('title')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            // comic chapter posts point at their chapter; that chapter points at the series
            $table->foreignId('comic_chapter_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'rating']);
            $table->index(['created_at']);
            $table->index(['views']);
            $table->index(['likes_count']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('caption')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('media');
    }
};
