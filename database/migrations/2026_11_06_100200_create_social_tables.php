<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
            $table->index('following_id');
        });

        Schema::create('comic_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comic_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'comic_id']);
        });

        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index('post_id');
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'created_at']);
        });

        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('viewer_key', 64);   // user id or a daily-rotating anon hash
            $table->date('viewed_on');
            $table->timestamps();

            $table->unique(['post_id', 'viewer_key', 'viewed_on']);
        });

        Schema::create('reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comic_chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->timestamps();

            $table->unique(['user_id', 'comic_id']);
        });

        Schema::create('notifications_app', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // recipient
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');                 // like | comment | follow | chapter | report
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_app');
        Schema::dropIfExists('reading_progress');
        Schema::dropIfExists('post_views');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('likes');
        Schema::dropIfExists('comic_follows');
        Schema::dropIfExists('follows');
    }
};
