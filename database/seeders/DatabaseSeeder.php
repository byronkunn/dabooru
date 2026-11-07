<?php

namespace Database\Seeders;

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
use App\Models\User;
use App\Support\DummyImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* ---------------- accounts ---------------- */

        $mira = User::firstOrCreate(['email' => 'demo@inkwell.test'], [
            'name' => 'mira.ink',
            'username' => 'mira_ink',
            'password' => Hash::make('password'),
            'avatar_path' => 'https://i.pravatar.cc/300?img=47',
            'bio' => 'Comics & character studies. Mostly ink, sometimes colour. NSFW sometimes, marked per post.',
            'location' => 'Portland',
            'website' => 'miraink.studio',
        ]);

        $crowd = collect([
            ['Rin Senpai', 'rin_senpai', 12, 'Making manga about cities at 2am. Explicit chapters are marked. 18+.'],
            ['Val', 'valdraws', 32, 'Digital painter. GIFs and loops. Commissions closed.'],
            ['Kuro', 'kurostudies', 5, 'Studies, sketch dumps, process videos. SFW only.'],
            ['Aya', 'aya_lines', 26, 'Lineart gremlin. OCs. 18+.'],
        ])->map(function (array $u, int $i) {
            return User::firstOrCreate(['username' => $u[1]], [
                'name' => $u[0],
                'email' => "{$u[1]}@inkwell.test",
                'password' => Hash::make('password'),
                'avatar_path' => 'https://i.pravatar.cc/300?img='.$u[2],
                'bio' => $u[3],
            ]);
        });

        $everyone = collect([$mira])->concat($crowd);
        $everyone->each(fn (User $u) => $u->forceFill(['email_verified_at' => now(), 'age_confirmed_at' => now()])->save());

        /* ---------------- follows ---------------- */

        foreach ($crowd as $a) {
            foreach ($everyone as $b) {
                if ($a->id !== $b->id) {
                    Follow::firstOrCreate(['follower_id' => $mira->id, 'following_id' => $a->id]);
                    Follow::firstOrCreate(['follower_id' => $b->id, 'following_id' => $a->id]);
                    Follow::firstOrCreate(['follower_id' => $a->id, 'following_id' => $mira->id]);
                }
            }
        }

        /* ---------------- works ---------------- */

        $works = [
            [$mira, 'image', 'sfw', 'Tea house', 'Watercolour, scanned at 600dpi.', 1039, 980, 7100],
            [$mira, 'image', 'suggestive', 'Late study', 'Lighting study, oil filter, too many layers.', 1027, 840, 6400],
            [$crowd[0], 'image', 'sfw', 'Mountain quiet', 'Did a plein-air study up near the lake. Never doing 6am again.', 1015, 2100, 18000],
            [$crowd[1], 'gif', 'explicit', 'loop study', 'Adjusted the loop timing — flagging explicit because of the second half.', 1005, 1400, 22000],
            [$crowd[2], 'video', 'sfw', 'timelapse — 4h in 40s', 'Full process video, with the embarrassing parts left in.', 1018, 920, 11000],
            [$crowd[0], 'album', 'suggestive', 'Autumn set · 7 pieces', 'Seven pieces from this month. Swipe through in the post.', 1035, 630, 5200],
            [$crowd[1], 'image', 'sfw', 'Morning market', 'Trying to draw fewer details, actually failing.', 1059, 1100, 9000],
            [$mira, 'image', 'explicit', 'After hours', 'Wanted it moodier than the last one. Explicit.', 1062, 2800, 31000],
            [$crowd[2], 'gif', 'suggestive', 'blink test', '12 frames, blink cycle. Took 3 hours to shade.', 1067, 410, 3100],
            [$crowd[0], 'image', 'sfw', 'Canopy', 'Ceiling of a temple garden.', 1074, 1900, 14000],
            [$crowd[1], 'video', 'explicit', 'process · 60s', 'Process for the piece from last week. Explicit edit.', 1080, 880, 7700],
            [$crowd[2], 'image', 'sfw', 'Quiet room', 'Reference from my own bedroom. Yes it looks like that.', 1084, 1300, 10000],
            [$crowd[3], 'image', 'sfw', 'Line weight tests', 'Testing brush textures. Probably keep two.', 1076, 610, 4800],
            [$crowd[3], 'gif', 'suggestive', 'hair wind loop', 'Wind loop, second pass.', 1069, 720, 5500],
            [$crowd[1], 'image', 'explicit', 'Study 14', 'Study 14 in the series. Explicit.', 1052, 2400, 27000],
            [$crowd[0], 'image', 'suggestive', 'Rooftop', 'Warm light, late summer.', 1040, 1000, 8200],
            [$crowd[2], 'album', 'sfw', 'Sketch dump · 12', 'Twelve pages of junk, some of it good.', 1047, 540, 4400],
        ];

        $posts = collect();
        foreach ($works as $i => [$user, $type, $rating, $title, $caption, $seed, $likes, $views]) {
            $post = Post::create([
                'user_id' => $user->id,
                'type' => $type,
                'rating' => $rating,
                'title' => $title,
                'caption' => $caption,
                'views' => $views,
                'created_at' => now()->subMinutes(17 * ($i + 1)),
                'updated_at' => now()->subMinutes(17 * ($i + 1)),
            ]);

            $count = $type === 'album' ? 7 : 1;
            for ($n = 0; $n < $count; $n++) {
                $media = $this->media($user, $type === 'video' ? 'video' : ($type === 'gif' ? 'gif' : 'image'), $seed + $n * 7, $n);
                PostMedia::create([
                    'post_id' => $post->id,
                    'media_id' => $media->id,
                    'position' => $n,
                    'caption' => $type === 'album' ? 'Piece '.($n + 1) : null,
                ]);
            }

            // seeded engagement
            $engaged = $everyone->filter(fn ($u) => $u->id !== $user->id)->shuffle()->take(random_int(1, 4));
            foreach ($engaged as $fan) {
                Like::firstOrCreate(['user_id' => $fan->id, 'post_id' => $post->id]);
                if (random_int(0, 2) === 0) {
                    Comment::create([
                        'post_id' => $post->id,
                        'user_id' => $fan->id,
                        'body' => collect([
                            'The lighting on this one is unfair. How long did the backdrop take?',
                            'Saving this for the colour notes. The shadow shift is so clean.',
                            'Jealous of this whole page, honestly.',
                            'That linework is so confident.',
                        ])->random(),
                    ]);
                }
            }
            $post->forceFill([
                'likes_count' => Like::where('post_id', $post->id)->count(),
                'comments_count' => Comment::where('post_id', $post->id)->count(),
            ])->save();

            $posts->push($post);
        }

        /* ---------------- comics ---------------- */

        $comics = [
            [$crowd[0], 'neon-after-dark', 'Neon After Dark', 'A barfly manga about two rivals who keep meeting at 2am. Slow burn, then not slow at all. Explicit content from chapter 9.', 'explicit', 12, 1043],
            [$mira, 'paper-lanterns', 'Paper Lanterns', 'A lantern festival, a quiet town, and two people who keep missing each other by one street.', 'suggestive', 4, 1036],
            [$crowd[3], 'wired', 'Wired', 'A city that runs on other people\'s dreams.', 'sfw', 1, 1041],
        ];

        foreach ($comics as [$user, $slug, $title, $desc, $rating, $chapters, $seed]) {
            $comic = Comic::firstOrCreate(['slug' => $slug], [
                'user_id' => $user->id,
                'title' => $title,
                'description' => $desc,
                'rating' => $rating,
                'status' => 'ongoing',
                'last_chapter_at' => now()->subHours(2),
            ]);

            for ($n = $chapters; $n >= 1; $n--) {
                $chapter = ComicChapter::firstOrCreate(['comic_id' => $comic->id, 'number' => $n], [
                    'title' => $n === $chapters ? 'After hours' : 'Chapter '.$n,
                    'rating' => $n > 8 ? 'explicit' : 'sfw',
                    'published_at' => now()->subDays(($chapters - $n) * 3),
                    'views' => random_int(300, 9000),
                ]);

                if ($chapter->pages()->count() === 0) {
                    for ($p = 1; $p <= 8; $p++) {
                        $media = $this->media($user, 'image', $seed + $p * 11 + $n, $p + $n);
                        ComicPage::create(['comic_chapter_id' => $chapter->id, 'media_id' => $media->id, 'position' => $p]);
                    }
                }

                $chapterPost = Post::firstOrCreate(
                    ['comic_chapter_id' => $chapter->id],
                    [
                        'user_id' => $user->id,
                        'type' => 'comic',
                        'rating' => $chapter->rating === 'explicit' ? 'explicit' : $comic->rating,
                        'title' => $title.' · Ch. '.$n,
                        'caption' => $n === $chapters ? 'New chapter is up.' : 'Chapter '.$n,
                        'views' => $chapter->views,
                        'created_at' => $chapter->published_at,
                    ]
                );

                if ($chapterPost->media()->count() === 0) {
                    $cover = $chapter->pages()->with('media')->first()?->media;
                    if ($cover) {
                        PostMedia::create(['post_id' => $chapterPost->id, 'media_id' => $cover->id, 'position' => 0]);
                    }
                }
            }

            ComicFollow::firstOrCreate(['user_id' => $mira->id, 'comic_id' => $comic->id]);
        }

        /* ---------------- demo direct message ---------------- */

        $conversation = Conversation::between($mira->id, $crowd[0]->id);
        if ($conversation->messages()->count() === 0) {
            $conversation->messages()->createMany([
                ['user_id' => $crowd[0]->id, 'body' => 'hey — working on ch. 12 panel cleanup'],
                ['user_id' => $crowd[0]->id, 'body' => "want to look at it before it goes up? explicit, so don't open on a train"],
                ['user_id' => $mira->id, 'body' => 'yes please, been waiting for this one'],
                ['user_id' => $crowd[0]->id, 'body' => 'that is it. thank you'],
            ]);
            $conversation->forceFill(['last_message_at' => now()->subMinutes(4)])->save();
        }

        /* ---------------- demo notifications ---------------- */
        if (\App\Models\AppNotification::count() === 0) {
            $miraPosts = Post::where('user_id', $mira->id)->take(3)->get();
            foreach ($miraPosts as $i => $mp) {
                foreach ($crowd->take(2) as $actor) {
                    \App\Models\AppNotification::create([
                        'user_id' => $mira->id,
                        'actor_id' => $actor->id,
                        'type' => $i === 1 ? 'comment' : 'like',
                        'subject_type' => Post::class,
                        'subject_id' => $mp->id,
                        'data' => ['post_id' => $mp->id],
                        'created_at' => now()->subMinutes(20 * ($i + 1)),
                    ]);
                }
            }
            foreach ($crowd as $actor) {
                \App\Models\AppNotification::create([
                    'user_id' => $mira->id,
                    'actor_id' => $actor->id,
                    'type' => 'follow',
                    'data' => [],
                    'created_at' => now()->subHours(3),
                ]);
            }
        }

        $this->command?->info('Seeded: '.$everyone->count().' artists, '.$posts->count().' works, '.Comic::count().' comics.');
        $this->command?->info('Demo login: demo@inkwell.test / password');
    }

    private function media(User $user, string $kind, int $seed, int $position): Media
    {
        $w = $kind === 'video' ? 1280 : 800;
        $h = $kind === 'video' ? 720 : (int) (800 / (1.2 + ($seed % 7) / 10));

        return Media::firstOrCreate(
            ['user_id' => $user->id, 'path' => $this->seedPath($seed)],
            [
                'disk' => 'public',
                'thumb_path' => $this->seedPath($seed + 5000),
                'kind' => $kind,
                'width' => $w,
                'height' => max(320, $h),
                'size_bytes' => 1_200_000,
                'status' => 'ready',
                'mime_type' => $kind === 'video' ? 'video/mp4' : 'image/jpeg',
            ]
        );
    }

    /**
     * Seed media points at remote placeholder URLs. The filesystems adapter
     * (see Media::url in the front end) renders absolute URLs untouched, so the
     * demo looks populated without shipping 100MB of sample images.
     */
    private function seedPath(int $seed): string
    {
        // picsum /seed/ returns a 302 to fastly. Use the id endpoint so the
        // browser receives the image in one hop, no redirect chain.
        return DummyImage::url($seed, 800, 1000);
    }
}
