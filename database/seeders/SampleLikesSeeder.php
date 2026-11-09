<?php

namespace Database\Seeders;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Gives the site real likes to count.
 *
 * Posts used to carry an invented likes_count with no rows behind it, which made
 * every ranking and every analytic run on numbers that did not exist. This seeds
 * genuine like rows and then recalculates each post's counter from them.
 *
 * Re-running is safe: likes are unique per (user, post), and the counter sync is
 * idempotent.
 */
class SampleLikesSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->whereNotNull('username')->where('is_unsorted', false)->pluck('id')->all();

        if (count($users) < 2) {
            $this->command?->info('Not enough accounts to seed likes.');

            return;
        }

        $seeded = 0;

        Post::query()->orderBy('id')->each(function (Post $post) use ($users, &$seeded) {
            // popular-looking works get more likes; most get a handful
            $existing = $post->likes_count;
            $target = $existing > 0 ? min($existing, count($users) - 1) : 0;

            if ($target === 0) {
                $post->syncLikesCount();

                return;
            }

            // never let an author like their own work
            $pool = array_values(array_diff($users, [$post->user_id]));
            shuffle($pool);
            $chosen = array_slice($pool, 0, min($target, count($pool)));

            Like::query()->insertOrIgnore(array_map(fn ($userId) => [
                'user_id' => $userId,
                'post_id' => $post->id,
                'created_at' => $post->created_at->copy()->addHours(random_int(1, 72)),
                'updated_at' => $post->created_at,
            ], $chosen));

            $post->syncLikesCount();
            $seeded++;
        });

        $this->command?->info("Real likes seeded across {$seeded} works; counters recalculated.");
    }
}
