<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chat_calls');
        DB::table('settings')->where('key', 'chat_calls')->delete();
    }

    public function down(): void
    {
        $creation = require __DIR__.'/2026_11_07_231100_create_chat_calls.php';
        $creation->up();
    }
};
