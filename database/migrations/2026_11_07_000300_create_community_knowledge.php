<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('category')->default('general');
            $table->text('wiki')->nullable();
            $table->string('wiki_rating')->default('sfw');
            $table->boolean('locked')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('merged_into_id')->nullable()->constrained('tags')->restrictOnDelete();
            $table->string('artist_status')->default('unverified');
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_url', 500)->nullable();
        });
        Schema::table('posts', fn (Blueprint $table) => $table->unsignedInteger('tag_version')->default(0));
        Schema::table('users', fn (Blueprint $table) => $table->boolean('contributions_restricted')->default(false));
        Schema::create('tag_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
        });
        Schema::create('tag_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('kind');
            $table->unique(['tag_id', 'related_tag_id', 'kind']);
        });
        Schema::create('wiki_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160)->unique();
            $table->text('body')->nullable();
            $table->string('rating')->default('sfw');
            $table->boolean('locked')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
        Schema::create('knowledge_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before');
            $table->json('after');
            $table->unsignedInteger('base_version');
            $table->string('summary', 500);
            $table->string('status')->default('pending');
            $table->string('review_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });
        Schema::create('knowledge_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->unique(['user_id', 'target_type', 'target_id']);
        });
        Schema::create('knowledge_discussions', function (Blueprint $table) {
            $table->id();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('hidden')->default(false);
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
        });
        Schema::create('knowledge_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('reason', 500);
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knowledge_reports', 'knowledge_discussions', 'knowledge_watches', 'knowledge_revisions', 'wiki_pages', 'tag_relations', 'tag_aliases'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('contributions_restricted'));
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('tag_version'));
        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropConstrainedForeignId('member_id');
            $table->dropColumn(['category', 'wiki', 'wiki_rating', 'locked', 'version', 'artist_status', 'source_url']);
        });
    }
};
