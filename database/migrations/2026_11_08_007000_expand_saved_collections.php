<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_collections', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->foreignId('cover_post_id')->nullable()->constrained('posts')->nullOnDelete();
        });
        Schema::table('bookmark_saved_collection', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('added_at')->nullable();
        });
        DB::table('saved_collections')->orderBy('id')->each(function ($collection) {
            $items = DB::table('bookmark_saved_collection as membership')
                ->join('bookmarks', 'bookmarks.id', '=', 'membership.bookmark_id')
                ->where('membership.saved_collection_id', $collection->id)
                ->orderBy('bookmarks.id')->get(['bookmarks.id', 'bookmarks.created_at']);
            foreach ($items as $position => $item) {
                DB::table('bookmark_saved_collection')->where('bookmark_id', $item->id)
                    ->where('saved_collection_id', $collection->id)
                    ->update(['position' => $position, 'added_at' => $item->created_at]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookmark_saved_collection', fn (Blueprint $table) => $table->dropColumn(['position', 'added_at']));
        Schema::table('saved_collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_post_id');
            $table->dropColumn('description');
        });
    }
};
