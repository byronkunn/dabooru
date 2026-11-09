<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram-style media groups: several photos sent together render as one album
 * bubble. Each message keeps its own media row; a shared group id ties them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('media_group')->nullable()->after('media_id');
            $table->unsignedTinyInteger('media_group_position')->nullable()->after('media_group');
            $table->index(['conversation_id', 'media_group']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'media_group']);
            $table->dropColumn(['media_group', 'media_group_position']);
        });
    }
};
