<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks that a reader has seen the post-registration setup step, whether they
 * filled it in or skipped it. Existing accounts are marked as done so nobody
 * who already has a working feed is interrupted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('setup_completed_at')->nullable()->after('age_confirmed_at');
        });

        DB::table('users')->whereNull('setup_completed_at')->update(['setup_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('setup_completed_at'));
    }
};
