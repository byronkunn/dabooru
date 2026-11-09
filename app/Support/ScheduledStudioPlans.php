<?php

namespace App\Support;

use App\Models\Comic;
use App\Models\ComicChapter;
use App\Models\DmcaNotice;
use App\Models\Media;
use App\Models\Post;
use App\Models\StudioPlan;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ScheduledStudioPlans
{
    public static function deliverDue(): array
    {
        $result = ['published' => 0, 'failed' => 0];
        $ids = StudioPlan::where('stage', 'scheduled')->where('delivery_status', 'pending')
            ->where('publish_at', '<=', now()->utc())->orderBy('publish_at')->orderBy('id')->limit(100)->pluck('id');

        foreach ($ids as $id) {
            $error = null;
            $status = DB::transaction(function () use ($id, &$error) {
                $plan = StudioPlan::lockForUpdate()->find($id);
                if (! $plan || $plan->stage !== 'scheduled' || $plan->delivery_status !== 'pending'
                    || ! $plan->publish_at || $plan->publish_at->isFuture()) {
                    return null;
                }
                try {
                    // A savepoint rolls publication back while retaining the plan lock for failure recording.
                    DB::transaction(function () use ($plan) {
                        self::publish($plan);
                        $plan->forceFill([
                            'stage' => 'published', 'delivery_status' => 'published',
                            'delivery_error' => null, 'published_at' => now()->utc(),
                        ])->save();
                    });

                    return 'published';
                } catch (Throwable $exception) {
                    $error = $exception;
                    $plan->refresh();
                    $message = $exception instanceof ValidationException
                        ? collect($exception->errors())->flatten()->first()
                        : 'Publication failed. The draft was kept private; review it and reschedule or contact support.';
                    $plan->forceFill(['delivery_status' => 'failed', 'delivery_error' => $message])->save();

                    return 'failed';
                }
            });
            if ($error) {
                Log::warning('Scheduled Studio publication failed.', ['plan_id' => $id]);
                report($error);
            }
            if ($status) {
                $result[$status]++;
            }
        }

        return $result;
    }

    private static function publish(StudioPlan $plan): void
    {
        $owner = User::lockForUpdate()->find($plan->user_id);
        self::require($owner && ! $owner->isBanned(), 'The owner is unavailable or banned. Publication was stopped.');
        self::require((bool) $plan->post_id !== (bool) $plan->comic_chapter_id, 'The linked draft is missing or ambiguous. Choose one draft and reschedule.');

        if ($plan->post_id) {
            $post = Post::withoutGlobalScope('published')->withTrashed()->lockForUpdate()->find($plan->post_id);
            self::require($post && $post->user_id === $owner->id && $post->is_draft
                && ! $post->trashed() && ! $post->moderation_hidden_at
                && ! $post->comic_chapter_id, 'The linked work is no longer an available owned draft.');
            self::require(! DmcaNotice::where('post_id', $post->id)->where('status', 'actioned')->exists(), 'An active takedown prevents publication.');
            self::mediaReady($post->media()->lockForUpdate()->pluck('media_id')->all(), $owner->id);
            $tags = $post->draft_tags ?? [];
            $post->forceFill(['is_draft' => false, 'draft_tags' => null, 'created_at' => now()->utc()])->save();
            Tag::syncFor($post, $tags, audit: false);

            return;
        }

        $chapter = ComicChapter::lockForUpdate()->find($plan->comic_chapter_id);
        self::require($chapter && ! $chapter->published_at, 'The linked chapter is missing or already published.');
        $comic = Comic::lockForUpdate()->find($chapter->comic_id);
        self::require($comic && $comic->user_id === $owner->id, 'The linked chapter is no longer owned by this account.');
        self::require(! Post::withoutGlobalScope('published')->withTrashed()->where('comic_chapter_id', $chapter->id)->exists(),
            'This chapter already has a publication or moderation record.');
        self::mediaReady($chapter->pages()->lockForUpdate()->pluck('media_id')->all(), $owner->id);
        ChapterPublishing::publish($comic, $chapter, $chapter->draft_tags ?? []);
    }

    private static function mediaReady(array $ids, int $ownerId): void
    {
        self::require($ids !== [], 'The draft has no media. Add ready uploads before rescheduling.');
        $media = Media::whereIn('id', $ids)->lockForUpdate()->get();
        self::require($media->count() === count(array_unique($ids)), 'A draft upload is missing.');
        foreach ($media as $item) {
            self::require($item->user_id === $ownerId && $item->status === 'ready', 'An upload is unavailable, still processing, or not owned by this account.');
            // Never move uploads or expose private DM media; Studio's guarded endpoint serves published draft files.
            self::require($item->disk !== 'local' || str_starts_with($item->path, "drafts/{$ownerId}/"), 'This draft contains private media that cannot be published.');
            self::require(! preg_match('~^(?:https?://|/)~i', $item->path) && ! str_contains($item->path, '..')
                && Storage::disk($item->disk)->exists($item->path), 'A draft upload file is missing or unsafe.');
        }
    }

    private static function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['publication' => $message]);
        }
    }
}
