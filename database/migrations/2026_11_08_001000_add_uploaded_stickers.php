<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sticker_packs', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->index();
        });
        Schema::create('stickers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sticker_pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->string('title', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stickers');
        Schema::table('sticker_packs', fn (Blueprint $table) => $table->dropColumn('is_public'));
    }
};
