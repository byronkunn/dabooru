<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug reports filed by people using the site, as opposed to the automatic
 * site_events telemetry. These carry what the reporter was doing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('bug');   // bug | idea | other
            $table->string('title', 160);
            $table->text('details')->nullable();
            // captured automatically so the reporter does not have to describe it
            $table->string('page_url', 500)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->string('viewport', 40)->nullable();
            $table->string('status', 20)->default('open'); // open | resolved
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};
