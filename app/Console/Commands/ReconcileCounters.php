<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Recalculate every post's denormalised counters from real rows.
 *
 * likes_count and comments_count are caches. Anything that writes them without
 * reconciling (old data, a failed request, a manual edit) shows up as drift, and
 * this puts it back.
 */
class ReconcileCounters extends Command
{
    protected $signature = 'posts:reconcile-counters {--dry-run : Show what would change without writing}';

    protected $description = 'Recalculate likes_count and comments_count from the likes and comments tables';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $drift = 0;

        Post::withoutGlobalScope('published')->withTrashed()->orderBy('id')->chunkById(200, function ($posts) use ($dry, &$drift) {
            foreach ($posts as $post) {
                $likes = $post->likes()->count();
                $comments = $post->comments()->count();

                if ((int) $post->likes_count === $likes && (int) $post->comments_count === $comments) {
                    continue;
                }

                $drift++;
                $this->line(sprintf('  #%d likes %d → %d, comments %d → %d',
                    $post->id, $post->likes_count ?? 0, $likes, $post->comments_count ?? 0, $comments));

                if (! $dry) {
                    $post->forceFill(['likes_count' => $likes, 'comments_count' => $comments])->save();
                }
            }
        });

        $this->info($drift === 0
            ? 'Counters already match.'
            : ($dry ? "{$drift} post(s) would change." : "{$drift} post(s) corrected."));

        return self::SUCCESS;
    }
}
