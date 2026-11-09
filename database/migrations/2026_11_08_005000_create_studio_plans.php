<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->string('stage', 20)->default('idea');
            $table->dateTime('deadline_at')->nullable();
            $table->dateTime('publish_at')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('comic_chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_status', 20)->nullable();
            $table->text('delivery_error')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'stage']);
            $table->index(['stage', 'delivery_status', 'publish_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_plans');
    }
};
