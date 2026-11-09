<?php

namespace App\Console\Commands;

use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Detach tags that nothing meaningfully uses.
 *
 * Auto-generated tags ("pocket clip", "side glance") are attached to one work
 * each. They never help a reader filter, follow or mute anything, and they crowd
 * the tag suggestions, the setup picks and the tag cloud. This detaches tags used
 * fewer than --min times and removes any left with no works.
 */
class PruneWeakTags extends Command
{
    protected $signature = 'tags:prune-weak {--min=2 : Keep tags attached to at least this many works}
                                         {--all : Also prune single-word tags}
                                         {--dry-run : Report what would be removed without writing}';

    protected $description = 'Detach tags used by fewer than N works and delete the now-empty ones';

    public function handle(): int
    {
        $min = max(1, (int) $this->option('min'));
        $dry = (bool) $this->option('dry-run');

        // filter in PHP: SQLite rejects HAVING on a non-aggregate select
        $weak = Tag::query()->withCount('posts')->get()
            ->filter(fn (Tag $tag) => $tag->posts_count < $min)
            ->when(! $this->option('all'), fn ($list) => $list->filter(fn (Tag $tag) => str_contains($tag->name, ' ')))
            ->values();

        if ($weak->isEmpty()) {
            $this->info('No weak tags found.');

            return self::SUCCESS;
        }

        $this->line("  {$weak->count()} weak tag(s), e.g. ".$weak->take(6)->pluck('name')->implode(', '));

        $this->line('Review the list before deleting: some genuine tags ("pixel art", "original character") are multi-word too.');

        if ($dry) {
            $weak->each(fn (Tag $tag) => $this->line("  · {$tag->name} ({$tag->posts_count})"));
            $this->info("{$weak->count()} tag(s) would be removed. Re-run without --dry-run to apply.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($weak) {
            $ids = $weak->pluck('id')->all();
            DB::table('post_tag')->whereIn('tag_id', $ids)->delete();
            Tag::whereIn('id', $ids)->delete();
        });

        $this->info("{$weak->count()} weak tag(s) removed.");

        return self::SUCCESS;
    }
}
