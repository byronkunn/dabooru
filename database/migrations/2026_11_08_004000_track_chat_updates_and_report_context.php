<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', fn (Blueprint $table) => $table->unsignedBigInteger('message_revision')->default(0));
        Schema::table('reports', fn (Blueprint $table) => $table->json('message_context')->nullable());
    }

    public function down(): void
    {
        Schema::table('reports', fn (Blueprint $table) => $table->dropColumn('message_context'));
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('message_revision'));
    }
};
