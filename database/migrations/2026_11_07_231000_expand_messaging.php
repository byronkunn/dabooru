<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('kind', 12)->default('direct');
            $table->string('title', 80)->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('show_history')->default(true);
            $table->unsignedSmallInteger('slow_seconds')->default(0);
            $table->string('request_status', 12)->default('accepted');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('discussion_id')->nullable();
            $table->boolean('join_approval')->default(false);
            $table->boolean('channel_inbox')->default(false);
            $table->unsignedBigInteger('inbox_channel_id')->nullable();
            $table->unsignedBigInteger('inbox_contact_id')->nullable();
            $table->unique(['inbox_channel_id', 'inbox_contact_id']);
            $table->unsignedInteger('auto_delete_seconds')->default(0);
            $table->index(['kind', 'is_public']);
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->string('role', 12)->default('member');
            $table->boolean('invited')->default(false);
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->boolean('join_pending')->default(false);
            $table->boolean('removed')->default(false);
            $table->unsignedBigInteger('history_after_id')->default(0);
            $table->boolean('muted')->default(false);
            $table->boolean('archived')->default(false);
            $table->boolean('pinned')->default(false);
            $table->boolean('marked_unread')->default(false);
            $table->string('folder', 40)->nullable();
            $table->timestamp('typing_until')->nullable();
        });

        Schema::create('conversation_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->timestamps();
            $table->unique(['conversation_id', 'title']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->unsignedBigInteger('shared_post_id')->nullable();
            $table->string('forwarded_from', 80)->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('silent')->default(false);
            $table->json('interactive')->nullable();
            $table->index(['conversation_id', 'topic_id', 'id']);
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->primary(['message_id', 'user_id']);
        });

        Schema::create('message_hides', function (Blueprint $table) {
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['message_id', 'user_id']);
        });

        Schema::create('message_choices', function (Blueprint $table) {
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('option_index');
            $table->primary(['message_id', 'user_id', 'option_index']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->text('message_evidence')->nullable();
            $table->foreignId('evidence_media_id')->nullable()->constrained('media')->nullOnDelete();
        });

        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->text('body');
            $table->boolean('silent')->default(false);
            $table->timestamp('send_at');
            $table->string('status', 12)->default('pending');
            $table->index(['status', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evidence_media_id');
            $table->dropColumn('message_evidence');
        });
        Schema::dropIfExists('message_choices');
        Schema::dropIfExists('message_hides');
        Schema::dropIfExists('message_reactions');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'topic_id', 'id']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['reply_to_id', 'topic_id', 'shared_post_id', 'forwarded_from', 'edited_at', 'pinned_at', 'removed_at', 'expires_at', 'silent', 'interactive']);
        });
        Schema::dropIfExists('conversation_topics');
        Schema::table('conversation_participants', fn (Blueprint $table) => $table->dropColumn([
            'role', 'invited', 'invited_by', 'join_pending', 'removed', 'history_after_id', 'muted', 'archived', 'pinned', 'marked_unread', 'folder', 'typing_until',
        ]));
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['kind', 'is_public']);
            $table->dropUnique(['inbox_channel_id', 'inbox_contact_id']);
            $table->dropColumn(['kind', 'title', 'is_public', 'show_history', 'slow_seconds', 'request_status', 'requested_by', 'discussion_id', 'join_approval', 'channel_inbox', 'inbox_channel_id', 'inbox_contact_id', 'auto_delete_seconds']);
        });
    }
};
