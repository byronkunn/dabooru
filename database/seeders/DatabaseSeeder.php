<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Support\SampleContent;
use Illuminate\Database\Seeder;

/**
 * Builds the library from the files in sfw/ — nothing else.
 *
 * There are no invented artists, titles or captions here. Each image and video
 * becomes one post whose title and author come from its file name when the file
 * name carries them; anonymous files are posted unattributed. Comics, likes,
 * comments, follows and messages are not fabricated, because there is no real
 * data behind them.
 *
 * Run with --fresh (or after migrate:fresh) for a clean library.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        $this->call(KnowledgeSeeder::class);
        $this->seedLibrary();

        $this->command?->info(sprintf(
            'Library loaded from sfw/: %d posts by %d artists.',
            Post::count(),
            User::count(),
        ));
        $this->command?->info('Anonymous files are posted unattributed. Add a name to a file name to credit it.');
    }

    private function seedLibrary(): void
    {
        $images = SampleContent::images();
        $videos = SampleContent::videos();

        $imagePosts = 0;
        $videoPosts = 0;

        // ---- images ------------------------------------------------------
        foreach ($images as $index => $image) {
            [$author, $title] = $this->describe($image['file']);

            $post = Post::create([
                'user_id' => $author?->id,
                'type' => 'image',
                'rating' => 'sfw',
                'title' => $title,
                'caption' => null,
                'views' => 0,
                'created_at' => now()->subMinutes(count($images) - $index),
                'updated_at' => now()->subMinutes(count($images) - $index),
            ]);

            $this->attach($post, $image, 'image', SampleContent::url('image', $image), $author);
            $imagePosts++;
        }

        // ---- videos ------------------------------------------------------
        foreach ($videos as $index => $video) {
            [$author, $title] = $this->describe($video['file']);

            $post = Post::create([
                'user_id' => $author?->id,
                'type' => 'video',
                'rating' => 'sfw',
                'title' => $title,
                'caption' => null,
                'views' => 0,
                'created_at' => now()->subMinutes(count($videos) - $index),
                'updated_at' => now()->subMinutes(count($videos) - $index),
            ]);

            $this->attach($post, $video, 'video', SampleContent::url('video', $video), $author, SampleContent::videoPoster($video));
            $videoPosts++;
        }

        $this->command?->info("  {$imagePosts} image posts, {$videoPosts} video posts.");
    }

    /**
     * A file's author (if its name names one) and its title (if its name has
     * words in it). Both may be null — that is the honest answer for a file
     * called `148a0f0eae39998a954d56c2290dc65e.png`.
     *
     * @return array{0:?User,1:?string}
     */
    private function describe(string $file): array
    {
        $author = SampleContent::authorFromFilename($file);
        $title = SampleContent::titleFromFilename($file);

        if (! $author) {
            return [null, $title];
        }

        $user = User::firstOrCreate(
            ['username' => $author['username']],
            [
                'name' => $author['name'],
                'email' => $author['username'].'@sfw.local',
                // this account has no real password; sign-in is only possible
                // through a reset. Replace or delete it when real artists join.
                'password' => bcrypt(bin2hex(random_bytes(16))),
                'avatar_path' => null,
            ]
        );

        $user->forceFill(['email_verified_at' => now(), 'age_confirmed_at' => now()])->save();

        return [$user, $title];
    }

    private function attach(Post $post, array $entry, string $kind, string $url, ?User $author, ?string $poster = null): void
    {
        $media = Media::create([
            // Unattributed files need an owner column value; use a dedicated
            // "unsorted" account rather than a fake artist.
            'user_id' => $author?->id ?? $this->unsortedUploader()->id,
            'disk' => 'public',
            'path' => $url,
            'thumb_path' => $poster,
            'kind' => $kind,
            'width' => $entry['w'] ?? null,
            'height' => $entry['h'] ?? null,
            'duration_seconds' => $entry['duration'] ?? null,
            'status' => 'ready',
            'mime_type' => $kind === 'video' ? 'video/mp4' : 'image/jpeg',
        ]);

        PostMedia::create(['post_id' => $post->id, 'media_id' => $media->id, 'position' => 0]);
    }

    /**
     * Uploads with no known author are owned by this account. It is not an
     * artist and is never shown as a byline (see App\Models\User::isUnsorted()).
     */
    private function unsortedUploader(): User
    {
        $user = User::firstOrCreate(
            ['username' => 'unsorted'],
            [
                'name' => 'Unsorted',
                'email' => 'unsorted@sfw.local',
                'password' => bcrypt(bin2hex(random_bytes(16))),
                'bio' => 'Files with no artist credited in their file name.',
            ]
        );

        $user->forceFill([
            'email_verified_at' => now(),
            'age_confirmed_at' => now(),
            'is_unsorted' => true,
        ])->save();

        return $user;
    }
}
