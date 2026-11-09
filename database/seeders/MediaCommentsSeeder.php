<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\DemoAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MediaCommentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Sample media comments are only available in local development and tests.');
        }
        $users = User::whereIn('username', DemoAccounts::usernames())->orderBy('id')->get();
        if ($users->isEmpty()) {
            throw new RuntimeException('No demo artists are available. Load the sample library before adding sample comments.');
        }
        $threads = [
            ['The composition gives this piece a clear focal point.', 'The framing is a useful detail to discuss.'],
            ['I would enjoy seeing a process breakdown for this work.', 'A sketch-to-finish comparison would be interesting.'],
            ['The visual style makes this work stand out in the gallery.', 'It would be fun to compare it with other works in this style.'],
            ['The tags make it easier to find related artwork.', 'Following those tags is a good way to explore more.'],
            ['This could fit nicely into a themed collection.', 'A shared collection could bring similar pieces together.'],
        ];
        $created = 0;
        DB::transaction(function () use ($users, $threads, &$created) {
            foreach (Post::orderBy('id')->lockForUpdate()->get() as $post) {
                foreach ($threads as $index => [$body, $reply]) {
                    $comment = Comment::withTrashed()->firstOrCreate([
                        'post_id' => $post->id, 'user_id' => $users[$index % $users->count()]->id,
                        'parent_id' => null, 'body' => '[Sample comment] '.$body,
                    ]);
                    $created += (int) $comment->wasRecentlyCreated;
                    if ($comment->trashed()) {
                        continue;
                    }
                    $response = Comment::withTrashed()->firstOrCreate([
                        'post_id' => $post->id, 'user_id' => $users[($index + 1) % $users->count()]->id,
                        'parent_id' => $comment->id, 'body' => '[Sample reply] '.$reply,
                    ]);
                    $created += (int) $response->wasRecentlyCreated;
                }
                $post->forceFill(['comments_count' => $post->comments()->count()])->save();
            }
        });
        $this->command?->info("Added {$created} sample comments/replies. Existing discussions were retained; private drafts were skipped.");
    }
}
