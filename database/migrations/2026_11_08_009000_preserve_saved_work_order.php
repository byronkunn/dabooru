<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookmarks', fn (Blueprint $table) => $table->unsignedBigInteger('saved_order')->nullable());
        Schema::table('bookmark_saved_collection', fn (Blueprint $table) => $table->unsignedBigInteger('added_order')->nullable());

        DB::table('saved_collections')->whereNotNull('cover_post_id')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('bookmark_saved_collection as membership')
                ->join('bookmarks as saved', 'saved.id', '=', 'membership.bookmark_id')
                ->whereColumn('membership.saved_collection_id', 'saved_collections.id')
                ->whereColumn('saved.user_id', 'saved_collections.user_id')
                ->whereColumn('saved.post_id', 'saved_collections.cover_post_id'))
            ->update(['cover_post_id' => null]);
    }

    public function down(): void
    {
        Schema::table('bookmark_saved_collection', fn (Blueprint $table) => $table->dropColumn('added_order'));
        Schema::table('bookmarks', fn (Blueprint $table) => $table->dropColumn('saved_order'));
    }
};
