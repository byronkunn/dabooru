<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample inbox for the admin, so messaging can be tested end to end.
 *
 * Every other account on the site opens a conversation with the admin and sends
 * a couple of messages. The admin never replies, so each thread shows an unread
 * badge and sits in the inbox. Re-running is safe: conversations that already
 * have messages are left alone.
 */
class SampleMessagesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'avetetsuya-studios')->first()
            ?? User::where('is_admin', true)->orderBy('id')->first();

        if (! $admin) {
            $this->command?->info('No admin account to receive the sample messages.');

            return;
        }

        $manifest = json_decode((string) file_get_contents(database_path('data/sfw/manifest.json')), true);
        $images = $manifest['image'] ?? [];
        $videos = array_values(array_filter($manifest['video'] ?? [], fn ($v) => ($v['duration'] ?? 0) > 0));

        $others = User::query()
            ->whereKeyNot($admin->id)
            ->whereNotIn('username', ['unsorted'])
            ->whereNotNull('username')
            ->orderBy('id')
            ->get();

        $openers = [
            'Hi — I wanted to report a post that was ripped from my gallery.',
            'Quick question about the content policy before I post.',
            'Hey, my chapter upload got stuck. Can you take a look?',
            'Someone is impersonating me with a similar handle.',
            'Thanks for approving my credit claim!',
            'Is there a way to edit the rating on an old post?',
            'The video on this page will not play for me.',
            'I think a work in my series was credited to the wrong artist.',
        ];

        $followUps = [
            'Let me know if you need the link to it.',
            'Sorry to bother you again — any update?',
            'I can send screenshots if that helps.',
            'No rush, just wanted to flag it.',
        ];

        $made = 0;

        foreach ($others as $index => $user) {
            $conversation = Conversation::between($admin->id, $user->id);

            if ($conversation->messages()->count() > 0) {
                continue;
            }

            $messages = [
                [$user->id, $openers[$index % count($openers)]],
                [$user->id, $followUps[$index % count($followUps)]],
            ];

            // every third account has already had a reply from the admin
            if ($index % 3 === 2) {
                $messages[] = [$admin->id, 'Thanks for flagging — I have logged it and will follow up.'];
                $messages[] = [$user->id, 'Appreciate it!'];
            }

            foreach ($messages as $n => [$sender, $body]) {
                $conversation->messages()->create([
                    'user_id' => $sender,
                    'body' => $body,
                    'rating' => 'sfw',
                    'created_at' => now()->subMinutes(($index + 1) * 37 - $n * 5),
                ]);
            }

            // every other thread also gets an image attachment from sfw/
            if ($index % 2 === 0) {
                $image = $images[$index % count($images)] ?? null;
                if ($image) {
                    $media = Media::create([
                        'user_id' => $user->id,
                        'disk' => 'public',
                        'path' => '/sfw/content/image/'.rawurlencode($image['file']),
                        'kind' => 'image',
                        'width' => $image['w'],
                        'height' => $image['h'],
                        'status' => 'ready',
                        'mime_type' => 'image/jpeg',
                    ]);

                    $conversation->messages()->create([
                        'user_id' => $user->id,
                        'body' => 'This is the post I mean — screenshot attached.',
                        'media_id' => $media->id,
                        'rating' => 'sfw',
                        'created_at' => now()->subMinutes(($index + 1) * 37 - 8),
                    ]);
                }
            }

            // one thread gets a video attachment too
            if ($index === 2 && $videos !== []) {
                $video = $videos[0];
                $media = Media::create([
                    'user_id' => $user->id,
                    'disk' => 'public',
                    'path' => '/sfw/content/video/'.rawurlencode($video['file']),
                    'thumb_path' => is_file(base_path('sfw/thumbs/'.preg_replace('/\.mp4$/i', '.jpg', $video['file'])))
                        ? '/sfw/content/thumbs/'.rawurlencode(preg_replace('/\.mp4$/i', '.jpg', $video['file']))
                        : null,
                    'kind' => 'video',
                    'width' => $video['w'] ?? null,
                    'height' => $video['h'] ?? null,
                    'duration_seconds' => $video['duration'] ?? null,
                    'status' => 'ready',
                    'mime_type' => 'video/mp4',
                ]);

                $conversation->messages()->create([
                    'user_id' => $user->id,
                    'body' => 'And here is the clip that would not play.',
                    'media_id' => $media->id,
                    'rating' => 'sfw',
                    'created_at' => now()->subMinutes(($index + 1) * 37 - 9),
                ]);
            }

            $conversation->forceFill(['last_message_at' => now()->subMinutes(($index + 1) * 37)])->save();
            $made++;
        }

        $this->command?->info("{$made} conversations seeded — sign in as {$admin->name} to read the inbox.");
    }
}
