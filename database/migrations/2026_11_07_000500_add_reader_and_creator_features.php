<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false);
            $table->json('draft_tags')->nullable();
            $table->foreignId('credited_artist_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('comic_chapters', fn (Blueprint $table) => $table->json('draft_tags')->nullable());
        Schema::table('saved_collections', fn (Blueprint $table) => $table->uuid('share_token')->nullable()->unique());

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('filters');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('comic_chapter_reads', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comic_chapter_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'comic_chapter_id']);
        });

        Schema::create('curated_shelves', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('description', 500)->nullable();
            $table->json('entries');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('artist_credit_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->text('evidence');
            $table->string('status')->default('pending')->index();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_credit_claims');
        Schema::dropIfExists('curated_shelves');
        Schema::dropIfExists('comic_chapter_reads');
        Schema::dropIfExists('saved_searches');
        Schema::table('saved_collections', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
        Schema::table('comic_chapters', fn (Blueprint $table) => $table->dropColumn('draft_tags'));
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credited_artist_id');
            $table->dropColumn(['is_draft', 'draft_tags']);
        });
    }
};
