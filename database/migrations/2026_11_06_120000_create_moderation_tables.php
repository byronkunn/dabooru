<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* A permanent record of every moderation action. This is what you show
           if a host, an ad network or a rights holder asks "what did you do and
           when". Never delete rows from here. */
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');                          // remove_post | ban_user | unban_user | dismiss_report | restore_post
            $table->string('target_type')->nullable();         // post | comment | user | message | report
            $table->unsignedBigInteger('target_id')->nullable();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();                  // a snapshot of what was removed
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });

        /* Reports can carry who acted and a link to the action taken. */
        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('handled_by');
            $table->index('created_at');
        });

        /* DMCA notices are a legal record, kept separately from the report queue. */
        Schema::create('dmca_notices', function (Blueprint $table) {
            $table->id();
            $table->string('reporter_name');
            $table->string('reporter_email');
            $table->string('rights_holder')->nullable();
            $table->string('url');                             // the offending URL
            $table->text('description');
            $table->boolean('good_faith')->default(false);     // the required statement
            $table->boolean('penalty_ack')->default(false);    // acknowledgement of perjury liability
            $table->string('status')->default('open');         // open | actioned | rejected
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        /* Blocks: both directions block interaction. */
        Schema::table('blocks', function (Blueprint $table) {
            $table->string('note')->nullable()->after('blocked_id');
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropIndex(['blocked_id']);
            $table->dropColumn('note');
        });
        Schema::dropIfExists('dmca_notices');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('resolved_at');
        });
        Schema::dropIfExists('moderation_actions');
    }
};
