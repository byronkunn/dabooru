<?php

namespace Database\Seeders;

use App\Models\KnowledgeRevision;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MediaTagsSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('data/sfw/tags.php');
        $updated = 0;
        $unmapped = [];

        Post::with('media.media')->chunkById(100, function ($posts) use ($data, &$updated, &$unmapped) {
            foreach ($posts as $post) {
                $lists = [];
                foreach ($post->media as $item) {
                    $stem = pathinfo(rawurldecode($item->media?->path ?? ''), PATHINFO_FILENAME);
                    if (!isset($data['media'][$stem])) {
                        $unmapped[] = $post->id;
                        continue 2;
                    }
                    [$group, $description] = $data['media'][$stem];
                    $lists[] = array_values(array_unique(array_merge(explode(', ', $description), $data['groups'][$group])));
                }
                if ($lists === []) {
                    $unmapped[] = $post->id;
                    continue;
                }

                $candidates = [];
                for ($index = 0; $index < max(array_map('count', $lists)); $index++) {
                    foreach ($lists as $list) {
                        if (isset($list[$index])) {
                            $candidates[] = $list[$index];
                        }
                    }
                }

                DB::transaction(function () use ($post, $candidates, &$updated) {
                    $locked = Post::lockForUpdate()->findOrFail($post->id);
                    $before = $locked->tags()->pluck('name')->all();
                    if (count($before) >= 30) {
                        return;
                    }
                    $names = array_slice(array_values(array_unique(array_merge($before, $candidates))), 0, 30);
                    $version = $locked->tag_version;
                    Tag::syncFor($locked, $names, audit: false);
                    KnowledgeRevision::create([
                        'target_type' => 'post', 'target_id' => $locked->id,
                        'before' => ['tags' => $before],
                        'after' => ['tags' => $locked->tags()->pluck('name')->all()],
                        'base_version' => $version,
                        'summary' => 'Added visually reviewed tags for the existing sample media.',
                        'status' => 'accepted',
                    ]);
                    $updated++;
                });
            }
        });

        $this->command?->info("Added reviewed tags to {$updated} media posts. Existing tags were kept.");
        if ($unmapped !== []) {
            $this->command?->warn('No reviewed tag mapping for posts: '.implode(', ', array_unique($unmapped)).'. These posts were not changed.');
        }
    }
}
