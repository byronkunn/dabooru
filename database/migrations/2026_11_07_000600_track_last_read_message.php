<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            // Keep the cursor even if the message is removed.
            $table->unsignedBigInteger('last_read_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', fn (Blueprint $table) => $table->dropColumn('last_read_message_id'));
    }
};
