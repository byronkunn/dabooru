<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->json('staff_roles')->nullable());
        Schema::table('reports', fn (Blueprint $table) => $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete());
        Schema::table('posts', fn (Blueprint $table) => $table->timestamp('moderation_hidden_at')->nullable());
        Schema::table('dmca_notices', fn (Blueprint $table) => $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete());

        Schema::create('site_events', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);
            $table->string('severity', 16);
            $table->string('exception', 180)->nullable();
            $table->string('route_name', 120)->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->timestamp('created_at')->index();
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_events');
        Schema::table('dmca_notices', fn (Blueprint $table) => $table->dropConstrainedForeignId('post_id'));
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('moderation_hidden_at'));
        Schema::table('reports', fn (Blueprint $table) => $table->dropConstrainedForeignId('assigned_to'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('staff_roles'));
    }
};
