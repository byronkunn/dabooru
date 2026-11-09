<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_views', fn (Blueprint $table) => $table->string('source', 24)->default('unknown'));
        Schema::create('studio_tracking', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
        });
        DB::table('studio_tracking')->insert(['started_at' => now()]);
        Schema::create('follower_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('change');
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
        });
        DB::table('follower_changes')->insertUsing(['user_id', 'change', 'created_at'],
            DB::table('follows')->selectRaw('following_id, 1, created_at'));
        Schema::create('chapter_readers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comic_chapter_id')->constrained()->cascadeOnDelete();
            $table->string('viewer_key', 64);
            $table->unsignedInteger('max_page')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['comic_chapter_id', 'viewer_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_readers');
        Schema::dropIfExists('follower_changes');
        Schema::dropIfExists('studio_tracking');
        Schema::table('post_views', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
