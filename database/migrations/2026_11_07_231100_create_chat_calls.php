<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('callee_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 5);
            $table->text('offer');
            $table->text('answer')->nullable();
            $table->timestamp('caller_seen_at');
            $table->timestamp('callee_seen_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['conversation_id', 'expires_at']);
            $table->index(['callee_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_calls');
    }
};
