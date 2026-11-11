<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('video_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('viewer_key', 64);
            $table->date('viewed_on');
            $table->timestamps();
            $table->unique(['post_id', 'viewer_key', 'viewed_on']);
        });
    }

    public function down(): void { Schema::dropIfExists('video_plays'); }
};
