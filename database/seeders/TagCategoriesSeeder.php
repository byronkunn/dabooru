<?php

namespace Database\Seeders;

use App\Models\KnowledgeRevision;
use App\Models\Tag;
use App\Support\TagCategories;
use App\Support\KnowledgeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $updated = 0;
        Tag::where('category', 'general')->where('locked', false)->chunkById(100, function ($tags) use (&$updated) {
            foreach ($tags as $tag) {
                $category = TagCategories::forName($tag->name);
                if ($category === 'general') {
                    continue;
                }
                DB::transaction(function () use ($tag, $category, &$updated) {
                    $current = Tag::lockForUpdate()->findOrFail($tag->id);
                    if ($current->category !== 'general' || $current->locked) {
                        return;
                    }
                    $version = $current->version;
                    $before = KnowledgeService::snapshot($current);
                    $current->update(['category' => $category, 'version' => $version + 1]);
                    KnowledgeRevision::create([
                        'target_type' => 'tag', 'target_id' => $current->id,
                        'before' => $before, 'after' => KnowledgeService::snapshot($current),
                        'base_version' => $version, 'summary' => 'Organized descriptive media tags into discovery categories.',
                        'status' => 'accepted',
                    ]);
                    $updated++;
                });
            }
        });
        $this->command?->info("Categorized {$updated} tags. Existing curated categories and locked tags were preserved.");
    }
}
