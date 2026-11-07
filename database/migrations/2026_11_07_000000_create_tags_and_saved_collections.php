<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
            $table->index(['tag_id', 'post_id']);
        });

        Schema::create('saved_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('bookmark_saved_collection', function (Blueprint $table) {
            $table->foreignId('bookmark_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_collection_id')->constrained()->cascadeOnDelete();
            $table->primary(['bookmark_id', 'saved_collection_id'], 'bookmark_collection_primary');
            $table->index(['saved_collection_id', 'bookmark_id'], 'collection_bookmark_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmark_saved_collection');
        Schema::dropIfExists('saved_collections');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
    }
};
