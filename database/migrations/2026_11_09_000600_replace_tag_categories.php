<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tags')->whereIn('category', ['character'])->update(['category' => 'characters']);
        DB::table('tags')->whereIn('category', ['series'])->update(['category' => 'franchise']);
        DB::table('tags')->whereIn('category', ['anatomy', 'appearance'])->update(['category' => 'anatomies']);
        DB::table('tags')->whereIn('category', ['position'])->update(['category' => 'poses']);
        DB::table('tags')->whereIn('category', ['setting'])->update(['category' => 'settings']);
        DB::table('tags')->whereIn('category', ['relationships', 'themes', 'content warnings'])->update(['category' => 'genre']);
        DB::table('tags')->whereIn('category', ['subject'])->update(['category' => 'entity']);
        DB::table('tags')->whereIn('category', ['medium', 'style', 'composition', 'general', 'clothing'])->update(['category' => 'entity']);
        DB::table('tags')->where('category', 'artist')->update(['category' => 'artist']);
    }

    public function down(): void
    {
        // The previous category assignment cannot be reconstructed after the consolidation.
    }
};
