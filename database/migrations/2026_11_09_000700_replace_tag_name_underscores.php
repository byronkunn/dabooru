<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('tags')
                ->whereRaw("name LIKE ? ESCAPE '!'", ['%!_%'])
                ->orderBy('id')
                ->get(['id', 'name'])
                ->each(function (object $tag): void {
                    $name = str_replace('_', '-', $tag->name);

                    if (DB::table('tags')->where('name', $name)->where('id', '!=', $tag->id)->exists()) {
                        throw new RuntimeException("Cannot rename tag {$tag->name}: {$name} already exists.");
                    }

                    DB::table('tags')->where('id', $tag->id)->update(['name' => $name]);
                });
        });
    }

    public function down(): void
    {
        // The original underscores cannot be reconstructed safely after future edits.
    }
};
