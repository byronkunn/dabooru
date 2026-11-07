<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\Bookmark;
use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\ComicFollow;
use App\Models\ComicPage;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Media;
use App\Models\Message;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\ReadingProgress;
use App\Models\User;
use App\Support\SampleContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedFollows();
        $this->seedWorks();
        $this->seedComics();
        $this->seedViewerActivity();
        $this->seedMessages();
        $this->seedNotifications();

        $this->command?->info('Seeded '.User::count().' accounts, '.Post::count().' posts, '.Comic::count().' comics.');
        $this->command?->info('Sign in on /login, or click a demo account: mira_ink, rin_senpai, viewer.');
    }

    /* ------------------------------------------------------------------ */

    private function avatar(int $i): string
    {
        $entry = SampleContent::pick(SampleContent::avatars(), $i);

        return $entry ? SampleContent::url('avatar', $entry) : '/avatar.svg';
    }

    private function banner(int $i): ?string
    {
        $entry = SampleContent::pick(SampleContent::banners(), $i);

        return $entry ? SampleContent::url('banner-image', $entry) : null;
    }

    private function seedUsers(): void
    {
        $accounts = [
            [
                'username' => 'mira_ink', 'name' => 'mira.ink', 'email' => 'demo@inkwell.test',
                'bio' => 'Comics & character studies. Mostly ink, sometimes colour. NSFW marked per post.',
                'location' => 'Portland', 'website' => 'miraink.studio', 'i' => 0,
            ],
            [
                'username' => 'rin_senpai', 'name' => 'Rin Senpai', 'email' => 'rin_senpai@inkwell.test',
                'bio' => 'Making manga about cities at 2am. Explicit chapters are marked. 18+.',
                'location' => 'Osaka', 'website' => null, 'i' => 3,
            ],
            [
                'username' => 'viewer', 'name' => 'Sam Viewer', 'email' => 'viewer@inkwell.test',
                'bio' => 'Just here to look at art and read comics.',
                'location' => null, 'website' => null, 'i' => 7,
            ],
        ];

        foreach ($accounts as $a) {
            $user = User::firstOrCreate(['username' => $a['username']], [
                'name' => $a['name'],
                'email' => $a['email'],
                'password' => Hash::make('password'),
                'avatar_path' => $this->avatar($a['i']),
                'banner_path' => $this->banner($a['i']),
                'bio' => $a['bio'],
                'location' => $a['location'],
                'website' => $a['website'],
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'age_confirmed_at' => now(),
                'show_explicit' => true,
            ])->save();
        }
    }

    private function seedFollows(): void
    {
        $mira = User::where('username', 'mira_ink')->firstOrFail();
        $rin = User::where('username', 'rin_senpai')->firstOrFail();
        $viewer = User::where('username', 'viewer')->firstOrFail();

        $pairs = [
            [$mira->id, $rin->id], [$rin->id, $mira->id],
            [$viewer->id, $mira->id], [$viewer->id, $rin->id],
        ];

        foreach ($pairs as [$follower, $following]) {
            Follow::firstOrCreate(['follower_id' => $follower, 'following_id' => $following]);
        }
    }

    /**
     * @param  array<int, array{0:string,1:string,2:string,3:string,4:int,5:int}>  $items
     *         [artist username, type, rating, title, likes, views]
     */
    private function makeWorks(string $username, array $items): void
    {
        if (Post::whereHas('user', fn ($q) => $q->where('username', $username))->exists()) {
            return;
        }

        $artist = User::where('username', $username)->firstOrFail();
        $imageIndex = 0;
        $videoIndex = 0;
        $order = 0;

        foreach ($items as [$artistUser, $type, $rating, $title, $likes, $views]) {
            $post = Post::create([
                'user_id' => $artistUser->id,
                'type' => $type,
                'rating' => $rating,
                'title' => $title,
                'caption' => $this->captionFor($type),
                'views' => $views,
                'likes_count' => $likes,
                'created_at' => now()->subMinutes(37 * (++$order)),
                'updated_at' => now()->subMinutes(37 * $order),
            ]);

            if ($type === 'video') {
                $video = SampleContent::pick(SampleContent::videos(), $videoIndex++);
                if ($video) {
                    $media = Media::create([
                        'user_id' => $artist->id,
                        'disk' => 'public',
                        'path' => SampleContent::url('video', $video),
                        'thumb_path' => SampleContent::url('thumbs', ['file' => str_replace('.mp4', '.jpg', $video['file'])]),
                        'kind' => 'video',
                        'width' => $video['w'],
                        'height' => $video['h'],
                        'duration_seconds' => $video['duration'] ?? null,
                        'status' => 'ready',
                        'mime_type' => 'video/mp4',
                    ]);
                    PostMedia::create(['post_id' => $post->id, 'media_id' => $media->id, 'position' => 0]);
                }

                continue;
            }

            // image / gif / album share the image folder
            $count = $type === 'album' ? 4 : 1;
            for ($n = 0; $n < $count; $n++) {
                $image = SampleContent::pick(SampleContent::images(), $imageIndex++);
                if (! $image) {
                    continue;
                }

                $media = Media::create([
                    'user_id' => $artist->id,
                    'disk' => 'public',
                    'path' => SampleContent::url('image', $image),
                    // the sample set has no real GIF files, so "gif" posts use
                    // a still image; the GIF viewer is exercised by uploads
                    'kind' => 'image',
                    'width' => $image['w'],
                    'height' => $image['h'],
                    'status' => 'ready',
                    'mime_type' => 'image/jpeg',
                ]);

                PostMedia::create([
                    'post_id' => $post->id,
                    'media_id' => $media->id,
                    'position' => $n,
                    'caption' => $type === 'album' ? 'Piece '.($n + 1) : null,
                ]);
            }
        }
    }

    private function seedWorks(): void
    {
        $mira = User::where('username', 'mira_ink')->firstOrFail();
        $rin = User::where('username', 'rin_senpai')->firstOrFail();

        $this->makeWorks('mira_ink', [
            [$mira, 'image', 'sfw', 'Tea house', 980, 7100],
            [$mira, 'image', 'suggestive', 'Late study', 840, 6400],
            [$mira, 'album', 'sfw', 'Autumn set', 630, 5200],
            [$mira, 'image', 'explicit', 'After hours', 2800, 31000],
            
            [$mira, 'image', 'sfw', 'Line weight tests', 610, 4800],
            [$mira, 'image', 'sfw', 'Quiet corner', 430, 3900],
            [$mira, 'gif', 'sfw', 'Colour block', 455, 4100],
        ]);

        $this->makeWorks('rin_senpai', [
            [$rin, 'image', 'sfw', 'Mountain quiet', 2100, 18000],
            [$rin, 'video', 'sfw', 'timelapse — 4h in 40s', 920, 11000],
            [$rin, 'image', 'suggestive', 'Rooftop', 1000, 8200],
            [$rin, 'image', 'sfw', 'Canopy', 1900, 14000],
            [$rin, 'video', 'suggestive', 'process cut', 880, 7700],
            [$rin, 'album', 'suggestive', 'Night studies', 540, 4400],
            [$rin, 'image', 'explicit', 'Study 14', 2400, 27000],
        ]);
    }

    private function seedComics(): void
    {
        $rin = User::where('username', 'rin_senpai')->firstOrFail();
        $mira = User::where('username', 'mira_ink')->firstOrFail();

        $series = [
            [$rin, 'neon-after-dark', 'Neon After Dark', 'Two rivals who keep meeting at 2am. Slow burn, then not slow at all. Explicit from chapter 9.', 'explicit', 3],
            [$mira, 'paper-lanterns', 'Paper Lanterns', 'A lantern festival, a quiet town, and two people who keep missing each other by one street.', 'suggestive', 2],
        ];

        $imageIndex = 20;

        foreach ($series as [$artist, $slug, $title, $description, $rating, $chapters]) {
            $comic = Comic::firstOrCreate(['slug' => $slug], [
                'user_id' => $artist->id,
                'title' => $title,
                'description' => $description,
                'rating' => $rating,
                'status' => 'ongoing',
                'last_chapter_at' => now()->subHours(2),
            ]);

            for ($n = 1; $n <= $chapters; $n++) {
                $chapter = ComicChapter::firstOrCreate(['comic_id' => $comic->id, 'number' => $n], [
                    'title' => $n === $chapters ? 'After hours' : 'Chapter '.$n,
                    'rating' => $rating === 'explicit' && $n > 1 ? 'explicit' : 'sfw',
                    'published_at' => now()->subDays(($chapters - $n) * 3),
                    'views' => random_int(300, 4000),
                ]);

                if ($chapter->pages()->count() === 0) {
                    for ($p = 1; $p <= 5; $p++) {
                        $image = SampleContent::pick(SampleContent::images(), $imageIndex++);
                        if (! $image) {
                            continue;
                        }

                        $media = Media::create([
                            'user_id' => $artist->id,
                            'disk' => 'public',
                            'path' => SampleContent::url('image', $image),
                            'kind' => 'image',
                            'width' => $image['w'],
                            'height' => $image['h'],
                            'status' => 'ready',
                            'mime_type' => 'image/jpeg',
                        ]);

                        ComicPage::create(['comic_chapter_id' => $chapter->id, 'media_id' => $media->id, 'position' => $p]);
                    }
                }

                // one feed post per published chapter, with the first page as its cover
                $chapterPost = Post::firstOrCreate(['comic_chapter_id' => $chapter->id], [
                    'user_id' => $artist->id,
                    'type' => 'comic',
                    'rating' => $chapter->rating,
                    'title' => $title.' · Ch. '.$n,
                    'caption' => $n === $chapters ? 'New chapter is up.' : 'Chapter '.$n,
                    'views' => $chapter->views,
                    'created_at' => $chapter->published_at,
                ]);

                if ($chapterPost->media()->count() === 0) {
                    $cover = $chapter->pages()->with('media')->first()?->media;
                    if ($cover) {
                        PostMedia::create(['post_id' => $chapterPost->id, 'media_id' => $cover->id, 'position' => 0]);
                    }
                }
            }

            ComicFollow::firstOrCreate(['user_id' => User::where('username', 'viewer')->value('id'), 'comic_id' => $comic->id]);
        }
    }

    /** The viewer account has a history: likes, comments, saves, reading progress. */
    private function seedViewerActivity(): void
    {
        $viewer = User::where('username', 'viewer')->firstOrFail();
        $posts = Post::whereNotIn('user_id', [$viewer->id])->orderByDesc('views')->take(8)->get();

        foreach ($posts as $i => $post) {
            Like::firstOrCreate(['user_id' => $viewer->id, 'post_id' => $post->id]);

            if ($i % 3 === 0) {
                Comment::firstOrCreate(
                    ['user_id' => $viewer->id, 'post_id' => $post->id],
                    ['body' => collect([
                        'The lighting on this one is unfair.',
                        'Saving this for the colour notes.',
                        'That linework is so confident.',
                        'This is my favourite thing you have posted.',
                    ])->random()]
                );
            }

            if ($i % 4 === 0) {
                Bookmark::firstOrCreate(['user_id' => $viewer->id, 'post_id' => $post->id]);
            }

            $post->forceFill([
                'likes_count' => Like::where('post_id', $post->id)->count(),
                'comments_count' => Comment::where('post_id', $post->id)->count(),
            ])->save();
        }

        // start the viewer a few pages into the latest chapter
        $comic = Comic::where('slug', 'neon-after-dark')->first();
        if ($comic && $chapter = $comic->chapters()->first()) {
            ReadingProgress::firstOrCreate(
                ['user_id' => $viewer->id, 'comic_id' => $comic->id],
                ['comic_chapter_id' => $chapter->id, 'page' => 2]
            );
        }
    }

    private function seedMessages(): void
    {
        $mira = User::where('username', 'mira_ink')->firstOrFail();
        $rin = User::where('username', 'rin_senpai')->firstOrFail();
        $viewer = User::where('username', 'viewer')->firstOrFail();

        $conversation = Conversation::between($mira->id, $rin->id);
        if ($conversation->messages()->count() === 0) {
            $conversation->messages()->createMany([
                ['user_id' => $rin->id, 'body' => 'hey — working on the next chapter cleanup'],
                ['user_id' => $rin->id, 'body' => 'want to look at it before it goes up? explicit, so don\'t open on a train'],
                ['user_id' => $mira->id, 'body' => 'yes please, been waiting for this one'],
                ['user_id' => $rin->id, 'body' => 'that is it. thank you'],
            ]);
            $conversation->forceFill(['last_message_at' => now()->subMinutes(4)])->save();
        }

        $viewerTalk = Conversation::between($viewer->id, $mira->id);
        if ($viewerTalk->messages()->count() === 0) {
            $viewerTalk->messages()->createMany([
                ['user_id' => $viewer->id, 'body' => 'Love the autumn set — is the second piece a print?'],
                ['user_id' => $mira->id, 'body' => 'it is, up on the shop next week'],
            ]);
            $viewerTalk->forceFill(['last_message_at' => now()->subHours(6)])->save();
        }
    }

    private function seedNotifications(): void
    {
        if (AppNotification::count() > 0) {
            return;
        }

        $mira = User::where('username', 'mira_ink')->firstOrFail();
        $rin = User::where('username', 'rin_senpai')->firstOrFail();
        $viewer = User::where('username', 'viewer')->firstOrFail();

        foreach (Post::where('user_id', $mira->id)->take(3)->get() as $i => $post) {
            AppNotification::create([
                'user_id' => $mira->id,
                'actor_id' => $viewer->id,
                'type' => $i === 1 ? 'comment' : 'like',
                'subject_type' => Post::class,
                'subject_id' => $post->id,
                'data' => ['post_id' => $post->id],
                'created_at' => now()->subMinutes(20 * ($i + 1)),
            ]);
        }

        AppNotification::create([
            'user_id' => $mira->id,
            'actor_id' => $rin->id,
            'type' => 'follow',
            'data' => [],
            'created_at' => now()->subHours(3),
        ]);
    }

    private function captionFor(string $type): string
    {
        return match ($type) {
            'video' => 'Full process cut — the embarrassing parts left in.',
            'album' => 'A few pieces from this month. Swipe through.',
            'gif' => 'Loop study, second pass.',
            'comic' => 'New chapter is up.',
            default => 'Study from this week.',
        };
    }
}
