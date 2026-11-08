<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sticker_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->json('post_ids');
            $table->timestamps();
            $table->unique(['user_id', 'title']);
        });

        Schema::table('messages', fn (Blueprint $table) => $table->foreignId('sticker_pack_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropConstrainedForeignId('sticker_pack_id'));
        Schema::dropIfExists('sticker_packs');
    }
};
